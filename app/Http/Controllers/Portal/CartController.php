<?php

namespace App\Http\Controllers\Portal;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Venue;
use App\Notifications\NewWebOrder;
use App\Notifications\OrderReceived;
use App\Services\Availability;
use App\Services\Distance;
use App\Support\Cart;
use App\Support\Tracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CartController extends Controller
{
    public function show()
    {
        $cart = Cart::get();
        $lines = Cart::lines();
        $period = Cart::period();
        $client = auth('client')->user();

        $availability = $period && $lines->isNotEmpty()
            ? Availability::forArticles($lines->pluck('article.id')->all(), ...$period)->map(fn ($a) => max(0, $a['available']))
            : collect();

        return view('portal.cart', [
            'cart' => $cart,
            'lines' => $lines,
            'period' => $period,
            'availability' => $availability,
            'venues' => Venue::where('active', true)->orderBy('name')->get(),
            'client' => $client,
            'discount' => $client?->isApproved() ? $client->discountPercent() : 0,
            'settings' => [
                'per_km' => Setting::number('transport_price_per_km'),
                'minimum' => Setting::number('transport_minimum'),
                'vat' => Setting::number('vat_rate'),
                'deposit' => Setting::number('deposit_amount'),
            ],
        ]);
    }

    public function add(Request $request, Article $article)
    {
        abort_unless($article->active, 404);
        $qty = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:10000']])['quantity'];
        Cart::add($article->id, $qty);
        Tracker::record('cart_add', ['article_id' => $article->id, 'value' => $qty]);

        return back()->with('status', "Añadido al pedido: {$qty} × {$article->name}.");
    }

    /** Guarda cantidades, fecha y destino del carrito (también se llama antes de solicitar). */
    public function update(Request $request)
    {
        $this->saveForm($request);

        return redirect()->route('portal.cart')->with('status', 'Pedido actualizado.');
    }

    /** Fecha del evento desde la barra «Planifica tu evento». */
    public function date(Request $request)
    {
        $v = $request->validate(['event_date' => ['nullable', 'date', 'after_or_equal:today']], [
            'event_date.after_or_equal' => 'La fecha del evento no puede ser anterior a hoy.',
        ]);
        Cart::put(['event_date' => $v['event_date'] ?? null]);

        return back()->with('status', $v['event_date'] ?? null ? 'Fecha guardada: ya ves las unidades libres para tu evento.' : 'Fecha quitada.');
    }

    public function remove(Article $article)
    {
        Cart::setQuantity($article->id, 0);

        return back()->with('status', 'Artículo quitado del pedido.');
    }

    /** Km y transporte estimados para el destino (JSON). */
    public function transport(Request $request)
    {
        $venue = Venue::where('active', true)->find($request->venue_id);
        try {
            $km = $venue?->distance_km ?? (int) Distance::fromBase($request->address, $request->city, $request->postal_code)['km'];
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        $trucks = 1;

        return response()->json(['km' => $km, 'amount' => Order::calculateTransport($km, $trucks)]);
    }

    public function checkout(Request $request)
    {
        $client = auth('client')->user();
        if (! $client?->isApproved()) {
            return redirect()->route('portal.cart')->with('error', 'Tu cuenta aún no está aprobada. Te avisaremos por email en cuanto lo esté.');
        }

        $this->saveForm($request);
        $cart = Cart::get();
        $lines = Cart::lines();
        $period = Cart::period();

        $errors = [];
        if ($lines->isEmpty()) {
            $errors['items'] = 'Tu pedido está vacío.';
        }
        if (! $period) {
            $errors['event_date'] = 'Indica la fecha del evento.';
        } elseif ($period[0] < today()->toDateString()) {
            $errors['event_date'] = 'La fecha del evento ya ha pasado.';
        }
        $venue = $cart['venue_id'] ? Venue::where('active', true)->find($cart['venue_id']) : null;
        if (! $venue && ! $cart['postal_code'] && ! $cart['city']) {
            $errors['destination'] = 'Elige una finca o indica la dirección del evento (al menos población o código postal).';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Km: los de la finca o calculados; si falla, el equipo lo revisará
        $km = $venue?->distance_km;
        if ($km === null) {
            try {
                $km = (int) Distance::fromBase($venue?->address ?? $cart['address'], $venue?->city ?? $cart['city'], $venue?->postal_code ?? $cart['postal_code'])['km'];
            } catch (RuntimeException) {
                $km = null;
            }
        }

        $order = DB::transaction(function () use ($client, $cart, $lines, $period, $venue, $km) {
            $short = Availability::shortages(
                $lines->map(fn ($l) => ['article_id' => $l['article']->id, 'quantity' => $l['quantity']]), ...$period
            );
            if ($short) {
                // Sin referencias internas para el cliente
                throw ValidationException::withMessages(['items' => array_map(
                    fn ($m) => preg_replace('/^\S+-\d{4} /', '', str_replace(' (contando las piezas de los combinados)', ' (contando las piezas de los conjuntos)', $m)), $short
                )]);
            }

            $order = Order::create([
                'number' => Order::nextNumber(),
                'client_id' => $client->id,
                'status' => OrderStatus::Requested,
                'source' => 'web',
                'event_date' => $cart['event_date'],
                'start_date' => $period[0],
                'end_date' => $period[1],
                'venue_id' => $venue?->id,
                'destination_address' => $venue?->address ?? $cart['address'],
                'destination_city' => $venue?->city ?? $cart['city'],
                'destination_postal_code' => $venue?->postal_code ?? $cart['postal_code'],
                'distance_km' => $km,
                'trucks' => 1,
                'transport_amount' => Order::calculateTransport($km, 1),
                'discount_percent' => $client->discountPercent(),
                'deposit_amount' => Setting::number('deposit_amount'),
                'vat_rate' => Setting::number('vat_rate'),
                'expires_at' => now()->addDays((int) Setting::number('prereservation_days')),
                'notes' => $cart['notes'] ? "Nota del cliente:\n".$cart['notes'] : null,
            ]);
            $order->lines()->createMany($lines->map(fn ($l) => [
                'article_id' => $l['article']->id, 'quantity' => $l['quantity'], 'unit_price' => $l['article']->rental_price,
            ])->all());

            return $order;
        });

        Cart::clear();
        Tracker::record('checkout', ['value' => $order->id]);
        rescue(fn () => $client->notify(new OrderReceived($order)), report: true);
        rescue(fn () => Notification::route('mail', Setting::get('notify_email'))->notify(new NewWebOrder($order)), report: true);

        return redirect()->route('portal.orders.show', $order->number)
            ->with('status', "¡Pedido {$order->number} recibido! El material queda pre-reservado. Te hemos enviado un email con el resumen.");
    }

    private function saveForm(Request $request): void
    {
        $v = $request->validate([
            'quantities' => ['nullable', 'array'],
            'quantities.*' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'event_date' => ['nullable', 'date', 'after_or_equal:today'],
            'venue_id' => ['nullable', Rule::exists('venues', 'id')->where('active', true)],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'regex:/^\d{5}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'event_date.after_or_equal' => 'La fecha del evento no puede ser anterior a hoy.',
            'postal_code.regex' => 'El código postal tiene 5 cifras.',
        ]);

        foreach ($v['quantities'] ?? [] as $id => $qty) {
            Cart::setQuantity((int) $id, (int) $qty);
        }
        Cart::put(collect($v)->only(['event_date', 'venue_id', 'address', 'postal_code', 'city', 'notes'])->all());
    }
}
