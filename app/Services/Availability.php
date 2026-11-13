<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\UnitStatus;
use App\Models\Article;
use App\Models\Order;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Disponibilidad de artículos entre dos fechas (ambas incluidas).
 *
 * Capacidad = unidades que no están de baja ni en reparación.
 * Reservado = para cada día del intervalo, suma de cantidades de los pedidos
 * que bloquean ese día; se toma el día con más reservas.
 * Un pedido entregado o devuelto bloquea desde su entrega hasta que se cierra,
 * aunque ya haya pasado la fecha de recogida.
 *
 * Productos combinados (`articles.is_bundle`): no tienen unidades propias. Un
 * combinado en un pedido reserva sus piezas (cantidad × piezas por combinado),
 * así que esas piezas dejan de estar disponibles sueltas. La disponibilidad de
 * un combinado es cuántos se pueden montar con las piezas libres.
 */
class Availability
{
    /** @return Collection<int,int> article_id => unidades utilizables (un combinado: cuántos se pueden montar) */
    public static function capacity(array $articleIds): Collection
    {
        return self::forArticles($articleIds, today(), today())->map(fn ($a) => $a['capacity']);
    }

    /**
     * Piezas de los combinados que haya entre los artículos indicados.
     * Un combinado sin piezas aparece con un array vacío.
     *
     * @return array<int, array<int,int>> bundle_id => [component_id => cantidad]
     */
    public static function components(array $articleIds): array
    {
        $articleIds = array_values(array_unique(array_map('intval', $articleIds)));
        if (! $articleIds) {
            return [];
        }
        $map = Article::withTrashed()->whereIn('id', $articleIds)->where('is_bundle', true)
            ->pluck('id')->mapWithKeys(fn ($id) => [(int) $id => []])->all();
        if ($map) {
            foreach (DB::table('article_components')->whereIn('bundle_id', array_keys($map))->get() as $row) {
                $map[(int) $row->bundle_id][(int) $row->component_id] = (int) $row->quantity;
            }
        }

        return $map;
    }

    /**
     * Pasa líneas (artículo, cantidad) a cantidades de piezas físicas,
     * desglosando los combinados.
     *
     * @param  iterable<array{article_id:int, quantity:int}>  $lines
     * @param  array<int, array<int,int>>|null  $components  resultado de components(); se calcula si falta
     * @return array<int,int> article_id físico => cantidad
     */
    public static function expand(iterable $lines, ?array $components = null): array
    {
        $lines = collect($lines)->map(fn ($l) => ['article_id' => (int) $l['article_id'], 'quantity' => (int) $l['quantity']]);
        $components ??= self::components($lines->pluck('article_id')->all());

        $out = [];
        foreach ($lines as $line) {
            $parts = $components[$line['article_id']] ?? [$line['article_id'] => 1];
            foreach ($parts as $id => $per) {
                $out[$id] = ($out[$id] ?? 0) + $line['quantity'] * $per;
            }
        }

        return $out;
    }

    /** Unidades utilizables de artículos con unidades propias. */
    private static function physicalCapacity(array $articleIds): Collection
    {
        return Unit::query()
            ->whereIn('article_id', $articleIds)
            ->whereNotIn('status', [UnitStatus::Retired, UnitStatus::Maintenance])
            ->selectRaw('article_id, count(*) as total')
            ->groupBy('article_id')
            ->pluck('total', 'article_id')
            ->map(fn ($v) => (int) $v);
    }

    /**
     * Máximo reservado en un mismo día por artículo físico, incluidas las
     * piezas reservadas a través de combinados.
     *
     * @return Collection<int,int> article_id => máximo reservado en un mismo día
     */
    public static function reserved(array $articleIds, $from, $to, ?int $exceptOrderId = null): Collection
    {
        $from = CarbonImmutable::parse($from)->startOfDay();
        $to = CarbonImmutable::parse($to)->startOfDay();
        $wanted = array_flip($articleIds);

        $orders = Order::query()
            ->blocking()
            ->when($exceptOrderId, fn ($q) => $q->whereKeyNot($exceptOrderId))
            ->where('start_date', '<=', $to->toDateString())
            ->where(fn ($q) => $q
                ->where('end_date', '>=', $from->toDateString())
                ->orWhereIn('status', OrderStatus::outOfWarehouse()))
            ->with('lines')
            ->get();

        $components = self::components($orders->flatMap->lines->pluck('article_id')->all());

        $perDay = []; // [article_id][Y-m-d] => qty
        foreach ($orders as $order) {
            $demand = array_intersect_key(self::expand($order->lines, $components), $wanted);
            if (! $demand) {
                continue;
            }
            $end = in_array($order->status, OrderStatus::outOfWarehouse(), true)
                ? $to
                : CarbonImmutable::parse($order->end_date)->min($to);
            $start = CarbonImmutable::parse($order->start_date)->max($from);
            foreach (CarbonPeriod::create($start, $end) as $day) {
                $d = $day->toDateString();
                foreach ($demand as $id => $qty) {
                    $perDay[$id][$d] = ($perDay[$id][$d] ?? 0) + $qty;
                }
            }
        }

        return collect($articleIds)->mapWithKeys(fn ($id) => [$id => isset($perDay[$id]) ? max($perDay[$id]) : 0]);
    }

    /** @return Collection<int,array{capacity:int,reserved:int,available:int}> */
    public static function forArticles(array $articleIds, $from, $to, ?int $exceptOrderId = null): Collection
    {
        $articleIds = array_values(array_unique(array_map('intval', $articleIds)));
        $components = self::components($articleIds);

        $physical = array_diff($articleIds, array_keys($components));
        foreach ($components as $parts) {
            $physical = array_merge($physical, array_keys($parts));
        }
        $physical = array_values(array_unique($physical));

        $capacity = self::physicalCapacity($physical);
        $reserved = self::reserved($physical, $from, $to, $exceptOrderId);

        return collect($articleIds)->mapWithKeys(function ($id) use ($components, $capacity, $reserved) {
            if (isset($components[$id])) {
                return [$id => self::bundleFigures($components[$id], $capacity, $reserved)];
            }
            $c = $capacity[$id] ?? 0;
            $r = $reserved[$id] ?? 0;

            return [$id => ['capacity' => $c, 'reserved' => $r, 'available' => $c - $r]];
        });
    }

    /**
     * Comprueba que haya material para unas líneas de pedido, contando que
     * varias líneas (sueltas o dentro de combinados) pueden pedir la misma pieza.
     * Bloquea las filas de los artículos implicados para serializar pedidos simultáneos.
     *
     * @param  iterable<array{article_id:int, quantity:int}>  $lines
     * @return string[] mensajes de lo que falta (vacío si todo cabe)
     */
    public static function shortages(iterable $lines, $from, $to, ?int $exceptOrderId = null): array
    {
        $lines = collect($lines)->map(fn ($l) => ['article_id' => (int) $l['article_id'], 'quantity' => (int) $l['quantity']]);
        $components = self::components($lines->pluck('article_id')->all());
        $demand = self::expand($lines, $components);
        $ids = array_values(array_unique(array_merge($lines->pluck('article_id')->all(), array_keys($demand))));

        DB::table('articles')->whereIn('id', $ids)->lockForUpdate()->get(['id']);
        $articles = Article::withTrashed()->whereIn('id', $ids)->get()->keyBy('id');
        $errors = [];

        foreach ($lines as $line) {
            if (isset($components[$line['article_id']]) && ! $components[$line['article_id']]) {
                $a = $articles[$line['article_id']];
                $errors[] = "{$a->reference} {$a->name}: es un combinado sin piezas definidas; no se puede reservar.";
            }
        }

        $direct = $lines->groupBy('article_id')->map->sum('quantity');
        $free = self::forArticles(array_keys($demand), $from, $to, $exceptOrderId);
        foreach ($demand as $id => $qty) {
            $available = $free[$id]['available'];
            if ($qty > $available) {
                $a = $articles[$id];
                $errors[] = $qty > ($direct[$id] ?? 0)
                    ? "{$a->reference} {$a->name}: hacen falta {$qty} (contando las piezas de los combinados) y solo hay ".max(0, $available).' disponibles en esas fechas.'
                    : "{$a->reference} {$a->name}: pides {$qty} y solo hay ".max(0, $available).' disponibles en esas fechas.';
            }
        }

        return $errors;
    }

    /** @param array<int,int> $parts */
    private static function bundleFigures(array $parts, Collection $capacity, Collection $reserved): array
    {
        if (! $parts) {
            return ['capacity' => 0, 'reserved' => 0, 'available' => 0];
        }
        $cap = PHP_INT_MAX;
        $free = PHP_INT_MAX;
        foreach ($parts as $id => $per) {
            $c = $capacity[$id] ?? 0;
            $cap = min($cap, intdiv($c, max(1, $per)));
            $free = min($free, intdiv(max(0, $c - ($reserved[$id] ?? 0)), max(1, $per)));
        }

        return ['capacity' => $cap, 'reserved' => $cap - $free, 'available' => $free];
    }
}
