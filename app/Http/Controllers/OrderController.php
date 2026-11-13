<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UnitStatus;
use App\Models\Article;
use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Venue;
use App\Notifications\ProformaSent;
use App\Services\Availability;
use App\Services\Distance;
use App\Support\Expedition;
use App\Support\Proforma;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        Order::expireOverdue();

        $orders = Order::query()
            ->with(['client', 'lines', 'venue'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('trade_name', 'like', "%{$term}%"))))
            ->when(! $request->status && ! $request->boolean('all'), fn ($q) => $q->whereIn('status', OrderStatus::blocking()))
            ->orderBy('start_date')
            ->paginate(25)
            ->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function create(Request $request)
    {
        $order = new Order([
            'client_id' => $request->client,
            'start_date' => $request->start,
            'end_date' => $request->end,
            'trucks' => 1,
            'discount_percent' => 0,
            'deposit_amount' => Setting::number('deposit_amount'),
        ]);
        if ($client = Client::with('category')->find($request->client)) {
            $order->discount_percent = $client->discountPercent();
        }
        if ($venue = Venue::find($request->venue)) {
            $order->fill([
                'venue_id' => $venue->id, 'destination_address' => $venue->address, 'destination_city' => $venue->city,
                'destination_postal_code' => $venue->postal_code, 'distance_km' => $venue->distance_km,
            ]);
        }

        return view('orders.form', $this->formData($order));
    }

    public function store(Request $request)
    {
        [$data, $lines] = $this->validated($request);

        $order = DB::transaction(function () use ($data, $lines) {
            $this->assertAvailable($lines, $data['start_date'], $data['end_date'], null, (int) $data['client_id']);

            $order = Order::create($data + [
                'number' => Order::nextNumber(),
                'status' => OrderStatus::Requested,
                'vat_rate' => Setting::number('vat_rate'),
                'withholding_rate' => Setting::number('withholding_rate'),
                'expires_at' => now()->addDays((int) Setting::number('prereservation_days')),
                'created_by' => auth()->id(),
            ]);
            $order->lines()->createMany($lines);

            return $order;
        });

        return redirect()->route('orders.show', $order)
            ->with('status', "Pedido {$order->number} creado. El material queda pre-reservado hasta el {$order->expires_at->format('d/m/Y H:i')}.");
    }

    public function show(Order $order)
    {
        if ($order->isOverdue()) {
            Order::expireOverdue();
            $order->refresh();
        }
        $order->load(['client', 'lines.article', 'creator', 'payer', 'venue', 'units.unit.article']);

        $availability = Availability::forArticles(
            $order->lines->pluck('article_id')->all(), $order->start_date, $order->end_date, $order->id
        );

        return view('orders.show', compact('order', 'availability'));
    }

    public function edit(Order $order)
    {
        abort_unless($order->status->isEditable(), 403, 'Este pedido ya no se puede modificar.');

        return view('orders.form', $this->formData($order->load('lines')));
    }

    public function update(Request $request, Order $order)
    {
        abort_unless($order->status->isEditable(), 403, 'Este pedido ya no se puede modificar.');
        [$data, $lines] = $this->validated($request);

        DB::transaction(function () use ($order, $data, $lines) {
            $this->assertAvailable($lines, $data['start_date'], $data['end_date'], $order->id, (int) $data['client_id']);
            $order->update($data);
            $order->lines()->delete();
            $order->lines()->createMany($lines);
        });

        return redirect()->route('orders.show', $order)->with('status', 'Pedido actualizado.');
    }

    /** Avanza al siguiente estado, cancela o reactiva. */
    public function transition(Request $request, Order $order)
    {
        $target = OrderStatus::from($request->validate(['status' => ['required', Rule::enum(OrderStatus::class)]])['status']);
        $current = $order->status;

        $allowed = match (true) {
            $target === OrderStatus::Cancelled => $current->canCancel(),
            $target === OrderStatus::Requested => $current === OrderStatus::Expired,
            default => ($current->next()[0] ?? null) === $target,
        };
        if (! $allowed || ($current->isExpirable() && $order->isOverdue() && $target !== OrderStatus::Cancelled)) {
            return back()->with('error', 'Ese cambio de estado no es posible ahora. Si la pre-reserva ha caducado, reactívala primero.');
        }

        DB::transaction(function () use ($order, $target) {
            $changes = ['status' => $target];

            if ($target === OrderStatus::Requested) { // reactivar una pre-reserva caducada
                $order->load('lines');
                $this->assertAvailable(
                    $order->lines->map->only('article_id', 'quantity')->all(),
                    $order->start_date, $order->end_date, $order->id, $order->client_id
                );
                $changes['expires_at'] = now()->addDays((int) Setting::number('prereservation_days'));
                $changes['expiry_alerted_at'] = null;
            }
            if ($target === OrderStatus::Cancelled) { // las unidades ya escaneadas para salir vuelven a estar disponibles
                foreach ($order->units()->whereNull('return_status')->with('unit')->get() as $row) {
                    $row->unit->update(['status' => UnitStatus::Available]);
                    $row->delete();
                }
            }
            if ($target === OrderStatus::Paid) {
                $changes['expires_at'] = null; // pagado: ya no caduca
                $changes['paid_at'] = now();
                $changes['paid_by'] = auth()->id();
            }
            $order->update($changes);
        });

        return back()->with('status', "Pedido {$order->number}: {$target->label()}.");
    }

    /** PDF de la proforma (se abre en el navegador). */
    public function proforma(Order $order)
    {
        return Proforma::pdf($order)->stream(Proforma::filename($order));
    }

    /** Envía la proforma por email al cliente y deja el pedido en «Proforma enviada». */
    public function sendProforma(Request $request, Order $order)
    {
        abort_unless(in_array($order->status, [OrderStatus::Accepted, OrderStatus::ProformaSent, OrderStatus::Paid], true), 403,
            'La proforma se envía una vez aprobado el pedido.');
        if ($order->isOverdue()) {
            return back()->with('error', 'La pre-reserva ha caducado: reactívala o amplía el plazo antes de enviar la proforma.');
        }
        $data = $request->validate([
            'email' => ['required', 'email'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            Notification::route('mail', $data['email'])->notify(new ProformaSent($order, $data['note'] ?? null));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se ha podido enviar el email: '.$e->getMessage());
        }

        $changes = ['proforma_sent_at' => now(), 'proforma_sent_to' => $data['email']];
        if ($order->status === OrderStatus::Accepted) {
            $changes['status'] = OrderStatus::ProformaSent;
        }
        $order->update($changes);

        return back()->with('status', "Proforma enviada a {$data['email']}.");
    }

    /** Datos de logística (horas, contacto en el lugar, notas). Se pueden cambiar hasta cerrar el pedido. */
    public function logistics(Request $request, Order $order)
    {
        abort_if(in_array($order->status, [OrderStatus::Closed, OrderStatus::Cancelled], true), 403);
        $order->update($request->validate([
            'delivery_time' => ['nullable', 'string', 'max:40'],
            'pickup_time' => ['nullable', 'string', 'max:40'],
            'site_contact_name' => ['nullable', 'string', 'max:255'],
            'site_contact_phone' => ['nullable', 'string', 'max:40'],
            'logistics_notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('status', 'Datos de logística guardados.');
    }

    /** Hoja de expedición en PDF (sin precios), una vez pagado el pedido. */
    public function expedition(Order $order)
    {
        abort_unless(in_array($order->status, [OrderStatus::Paid, OrderStatus::Delivered, OrderStatus::Returned, OrderStatus::Closed], true), 403,
            'La hoja de expedición está disponible cuando el pedido está pagado.');

        return Expedition::pdf($order)->stream(Expedition::filename($order));
    }

    /** Amplía el plazo de la pre-reserva. */
    public function extend(Request $request, Order $order)
    {
        abort_unless($order->status->isExpirable(), 403);
        $days = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:60']])['days'];
        $base = $order->expires_at && $order->expires_at->isFuture() ? $order->expires_at : now();
        $order->update(['expires_at' => $base->copy()->addDays($days), 'expiry_alerted_at' => null]);

        return back()->with('status', "Pre-reserva ampliada hasta el {$order->expires_at->format('d/m/Y H:i')}.");
    }

    /** Km por carretera desde la base (JSON para el botón del formulario). */
    public function distance(Request $request)
    {
        $address = trim((string) $request->input('address'));
        $city = trim((string) $request->input('city'));
        $postalCode = trim((string) $request->input('postal_code'));

        try {
            if (! Distance::configured()) {
                throw new \RuntimeException('Falta la clave de OpenRouteService en el servidor.');
            }
            if ($address === '' && $city === '' && $postalCode === '') {
                throw new \RuntimeException('Escribe primero la dirección o la población del destino.');
            }

            return response()->json(Distance::fromBase($address, $city, $postalCode));
        } catch (ConnectionException) {
            return response()->json(['error' => 'El servicio de mapas no responde ahora mismo. Introduce los km a mano.'], 422);
        } catch (QueryException $e) {
            report($e);

            return response()->json(['error' => 'Error interno al calcular la distancia.'], 500);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function formData(Order $order): array
    {
        $articles = Article::query()
            ->where('active', true)
            ->orWhereIn('id', $order->lines->pluck('article_id'))
            ->with('category')
            ->orderBy('reference')
            ->get();

        return [
            'order' => $order,
            'clients' => Client::with('category')->orderBy('name')->get(['id', 'name', 'trade_name', 'client_category_id']),
            'venues' => Venue::where('active', true)->orWhere('id', $order->venue_id)->orderBy('name')->get(),
            'articles' => $articles,
            'mapsEnabled' => Distance::configured(),
            'settings' => [
                'per_km' => Setting::number('transport_price_per_km'),
                'minimum' => Setting::number('transport_minimum'),
            ],
        ];
    }

    /** @return array{0: array, 1: array<int, array{article_id:int, quantity:int, unit_price:float}>} */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'event_date' => ['nullable', 'date', 'required_without_all:start_date,end_date'],
            'start_date' => ['nullable', 'date', 'required_without:event_date'],
            'end_date' => ['nullable', 'date', 'required_without:event_date', 'after_or_equal:start_date'],
            'venue_id' => ['nullable', Rule::exists('venues', 'id')],
            'destination_address' => ['nullable', 'string', 'max:255'],
            'destination_city' => ['nullable', 'string', 'max:255'],
            'destination_postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'distance_km' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'trucks' => ['required', 'integer', 'min:1', 'max:20'],
            'transport_amount' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.article_id' => ['nullable', Rule::exists('articles', 'id')],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ], [
            'lines.required' => 'Añade al menos un artículo.',
            'end_date.after_or_equal' => 'La recogida no puede ser anterior a la entrega.',
            'event_date.required_without_all' => 'Indica la fecha del evento o las fechas de entrega y recogida.',
            'start_date.required_without' => 'Indica la fecha de entrega (o la del evento).',
            'end_date.required_without' => 'Indica la fecha de recogida (o la del evento).',
            'destination_postal_code.regex' => 'El código postal tiene 5 cifras.',
        ]);

        // Agrupar líneas por artículo e ignorar filas vacías
        $prices = Article::whereIn('id', collect($v['lines'])->pluck('article_id')->filter())->pluck('rental_price', 'id');
        $lines = collect($v['lines'])
            ->filter(fn ($l) => ! empty($l['article_id']) && ! empty($l['quantity']))
            ->groupBy('article_id')
            ->map(fn ($group, $articleId) => [
                'article_id' => (int) $articleId,
                'quantity' => (int) $group->sum('quantity'),
                'unit_price' => (float) ($group->first()['unit_price'] ?? $prices[$articleId] ?? 0),
            ])
            ->values()
            ->all();

        if (! $lines) {
            throw ValidationException::withMessages(['lines' => 'Añade al menos un artículo con cantidad.']);
        }

        // Evento en fin de semana: reserva mínima de viernes a domingo
        [$start, $end] = Order::weekendBlock($v['event_date'] ?? null, $v['start_date'] ?? null, $v['end_date'] ?? null);
        if (! empty($v['event_date']) && ($v['event_date'] < $start || $v['event_date'] > $end)) {
            throw ValidationException::withMessages(['event_date' => 'La fecha del evento tiene que estar entre la entrega y la recogida.']);
        }

        $manual = $request->boolean('transport_manual');
        $data = [
            'client_id' => $v['client_id'],
            'event_date' => $v['event_date'] ?? null,
            'start_date' => $start,
            'end_date' => $end,
            'venue_id' => $v['venue_id'] ?? null,
            'destination_address' => $v['destination_address'] ?? null,
            'destination_city' => $v['destination_city'] ?? null,
            'destination_postal_code' => $v['destination_postal_code'] ?? null,
            'distance_km' => $v['distance_km'] ?? null,
            'trucks' => $v['trucks'],
            'transport_manual' => $manual,
            'transport_amount' => $manual
                ? (float) ($v['transport_amount'] ?? 0)
                : Order::calculateTransport(isset($v['distance_km']) ? (float) $v['distance_km'] : null, (int) $v['trucks']),
            'discount_percent' => $v['discount_percent'] ?? 0,
            'deposit_amount' => $v['deposit_amount'] ?? 0,
            'notes' => $v['notes'] ?? null,
        ];

        return [$data, $lines];
    }

    private function assertAvailable(array $lines, $from, $to, ?int $exceptOrderId, int $clientId): void
    {
        $errors = array_merge(
            Availability::exclusiveErrors($lines, $clientId),
            Availability::shortages($lines, $from, $to, $exceptOrderId),
        );
        if ($errors) {
            throw ValidationException::withMessages(['lines' => $errors]);
        }
    }
}
