<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Order;
use Illuminate\Support\Collection;

/** Carrito de la web de clientes, guardado en la sesión hasta que se solicita el pedido. */
class Cart
{
    private const KEY = 'portal_cart';

    private const DEFAULTS = [
        'items' => [], 'event_date' => null, 'venue_id' => null,
        'address' => null, 'postal_code' => null, 'city' => null, 'notes' => null,
    ];

    public static function get(): array
    {
        return array_merge(self::DEFAULTS, session(self::KEY, []));
    }

    public static function put(array $data): void
    {
        session([self::KEY => array_merge(self::get(), $data)]);
    }

    public static function setQuantity(int $articleId, int $qty): void
    {
        $items = self::get()['items'];
        if ($qty > 0) {
            $items[$articleId] = min($qty, 10000);
        } else {
            unset($items[$articleId]);
        }
        self::put(['items' => $items]);
    }

    public static function add(int $articleId, int $qty): void
    {
        self::setQuantity($articleId, (self::get()['items'][$articleId] ?? 0) + $qty);
    }

    public static function count(): int
    {
        return array_sum(self::get()['items']);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }

    /** @return Collection<int, array{article: Article, quantity: int}> */
    public static function lines(): Collection
    {
        $items = self::get()['items'];
        if (! $items) {
            return collect();
        }

        return Article::with('mainPhoto', 'category')
            ->where('active', true)
            ->whereIn('id', array_keys($items))
            ->orderBy('reference')
            ->get()
            ->map(fn (Article $a) => ['article' => $a, 'quantity' => (int) $items[$a->id]]);
    }

    /** Entrega y recogida según la fecha del evento (fin de semana: de viernes a domingo). */
    public static function period(): ?array
    {
        $event = self::get()['event_date'];

        return $event ? Order::weekendBlock($event, null, null) : null;
    }
}
