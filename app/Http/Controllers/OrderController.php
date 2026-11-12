<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Article;
use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        Order::expireOverdue();

        $orders = Order::query()
            ->with(['client', 'lines'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$term}%"))))
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

        return view('orders.form', $this->formData($order));
    }

    public function store(Request $request)
    {
        [$data, $lines] = $this->validated($request);

        $order = DB::transaction(function () use ($data, $lines) {
            $this->assertAvailable($lines, $data['start_date'], $data['end_date']);

            $order = Order::create($data + [
                'number' => Order::nextNumber(),
                'status' => OrderStatus::Requested,
                'vat_rate' => Setting::number('vat_rate'),
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
        $order->load(['client', 'lines.article', 'creator']);

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
            $this->assertAvailable($lines, $data['start_date'], $data['end_date'], $order->id);
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
                    $order->start_date, $order->end_date, $order->id
                );
                $changes['expires_at'] = now()->addDays((int) Setting::number('prereservation_days'));
            }
            if ($target === OrderStatus::Paid) {
                $changes['expires_at'] = null; // pagado: ya no caduca
            }
            $order->update($changes);
        });

        return back()->with('status', "Pedido {$order->number}: {$target->label()}.");
    }

    /** Amplía el plazo de la pre-reserva. */
    public function extend(Request $request, Order $order)
    {
        abort_unless($order->status->isExpirable(), 403);
        $days = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:60']])['days'];
        $base = $order->expires_at && $order->expires_at->isFuture() ? $order->expires_at : now();
        $order->update(['expires_at' => $base->copy()->addDays($days)]);

        return back()->with('status', "Pre-reserva ampliada hasta el {$order->expires_at->format('d/m/Y H:i')}.");
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
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'articles' => $articles,
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
            'event_date' => ['nullable', 'date'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'destination_address' => ['nullable', 'string', 'max:255'],
            'destination_city' => ['nullable', 'string', 'max:255'],
            'distance_km' => ['nullable', 'numeric', 'min:0', 'max:5000'],
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

        $manual = $request->boolean('transport_manual');
        $data = [
            'client_id' => $v['client_id'],
            'event_date' => $v['event_date'] ?? null,
            'start_date' => $v['start_date'],
            'end_date' => $v['end_date'],
            'destination_address' => $v['destination_address'] ?? null,
            'destination_city' => $v['destination_city'] ?? null,
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

    private function assertAvailable(array $lines, $from, $to, ?int $exceptOrderId = null): void
    {
        $ids = array_column($lines, 'article_id');
        DB::table('articles')->whereIn('id', $ids)->lockForUpdate()->get(['id']); // serializa pedidos simultáneos

        $availability = Availability::forArticles($ids, $from, $to, $exceptOrderId);
        $names = Article::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');

        $errors = [];
        foreach ($lines as $line) {
            $free = $availability[$line['article_id']]['available'];
            if ($line['quantity'] > $free) {
                $a = $names[$line['article_id']];
                $errors[] = "{$a->reference} {$a->name}: pides {$line['quantity']} y solo hay ".max(0, $free).' disponibles en esas fechas.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages(['lines' => $errors]);
        }
    }
}
