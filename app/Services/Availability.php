<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UnitStatus;
use App\Models\Order;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Disponibilidad de artículos entre dos fechas (ambas incluidas).
 *
 * Capacidad = unidades que no están de baja ni en reparación.
 * Reservado = para cada día del intervalo, suma de cantidades de los pedidos
 * que bloquean ese día; se toma el día con más reservas.
 * Un pedido entregado o devuelto bloquea desde su entrega hasta que se cierra,
 * aunque ya haya pasado la fecha de recogida.
 */
class Availability
{
    /** @return Collection<int,int> article_id => unidades utilizables */
    public static function capacity(array $articleIds): Collection
    {
        return Unit::query()
            ->whereIn('article_id', $articleIds)
            ->whereNotIn('status', [UnitStatus::Retired, UnitStatus::Maintenance])
            ->selectRaw('article_id, count(*) as total')
            ->groupBy('article_id')
            ->pluck('total', 'article_id')
            ->map(fn ($v) => (int) $v);
    }

    /** @return Collection<int,int> article_id => máximo reservado en un mismo día */
    public static function reserved(array $articleIds, $from, $to, ?int $exceptOrderId = null): Collection
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();

        $orders = Order::query()
            ->blocking()
            ->when($exceptOrderId, fn ($q) => $q->whereKeyNot($exceptOrderId))
            ->where('start_date', '<=', $to->toDateString())
            ->where(fn ($q) => $q
                ->where('end_date', '>=', $from->toDateString())
                ->orWhereIn('status', OrderStatus::outOfWarehouse()))
            ->with(['lines' => fn ($q) => $q->whereIn('article_id', $articleIds)])
            ->get();

        $perDay = []; // [article_id][Y-m-d] => qty
        foreach ($orders as $order) {
            $end = in_array($order->status, OrderStatus::outOfWarehouse(), true)
                ? $to
                : CarbonImmutable::parse($order->end_date)->min($to);
            $start = CarbonImmutable::parse($order->start_date)->max($from);
            foreach (CarbonPeriod::create($start, $end) as $day) {
                $d = $day->toDateString();
                foreach ($order->lines as $line) {
                    $perDay[$line->article_id][$d] = ($perDay[$line->article_id][$d] ?? 0) + $line->quantity;
                }
            }
        }

        return collect($articleIds)->mapWithKeys(fn ($id) => [$id => isset($perDay[$id]) ? max($perDay[$id]) : 0]);
    }

    /** @return Collection<int,array{capacity:int,reserved:int,available:int}> */
    public static function forArticles(array $articleIds, $from, $to, ?int $exceptOrderId = null): Collection
    {
        $capacity = self::capacity($articleIds);
        $reserved = self::reserved($articleIds, $from, $to, $exceptOrderId);

        return collect($articleIds)->mapWithKeys(function ($id) use ($capacity, $reserved) {
            $c = $capacity[$id] ?? 0;
            $r = $reserved[$id] ?? 0;

            return [$id => ['capacity' => $c, 'reserved' => $r, 'available' => $c - $r]];
        });
    }
}
