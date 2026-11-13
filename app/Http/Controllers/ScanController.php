<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UnitStatus;
use App\Models\Article;
use App\Models\Order;
use App\Models\OrderUnit;
use App\Models\Unit;
use App\Services\Availability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Salida y entrada de material escaneando los QR de las unidades.
 * Salida: pedido pagado → al terminar pasa a «Entregado».
 * Entrada: pedido entregado → al terminar pasa a «Devuelto».
 */
class ScanController extends Controller
{
    public const MODES = ['salida' => 'out', 'entrada' => 'in'];

    public function show(Order $order, string $mode)
    {
        $this->authorizeMode($order, $mode);

        return view('scan.show', ['order' => $order, 'mode' => $mode] + $this->state($order));
    }

    /** Escaneo (o código escrito a mano). Devuelve JSON con el aviso y la lista actualizada. */
    public function scan(Request $request, Order $order, string $mode): JsonResponse
    {
        $this->authorizeMode($order, $mode);
        $code = trim((string) $request->validate(['code' => ['required', 'string', 'max:500']])['code']);

        [$level, $message] = DB::transaction(function () use ($order, $mode, $code) {
            $unit = $this->findUnit($code);
            if (! $unit) {
                return ['error', "No se reconoce el código «{$code}»."];
            }
            $unit = Unit::whereKey($unit->id)->lockForUpdate()->first();
            $label = "{$unit->code} {$unit->article->name}";
            $existing = OrderUnit::where('order_id', $order->id)->where('unit_id', $unit->id)->first();

            return $mode === 'salida'
                ? $this->checkOut($order, $unit, $existing, $label)
                : $this->checkIn($unit, $existing, $label);
        });

        return $this->respond($order, $mode, $level, $message);
    }

    /** Cambiar a mano cómo vuelve una unidad (entrada) o quitarla de la salida. */
    public function update(Request $request, Order $order, string $mode, Unit $unit): JsonResponse
    {
        $this->authorizeMode($order, $mode);
        $row = OrderUnit::where('order_id', $order->id)->where('unit_id', $unit->id)->firstOrFail();
        $label = "{$unit->code} {$unit->article->name}";

        if ($mode === 'salida') {
            abort_unless($row->isOut(), 422);
            DB::transaction(function () use ($row, $unit) {
                $row->delete();
                $unit->update(['status' => UnitStatus::Available]);
            });

            return $this->respond($order, $mode, 'ok', "{$label}: quitada de la salida.");
        }

        $status = $request->validate(['status' => ['required', 'in:ok,damaged,lost,pending']])['status'];
        DB::transaction(function () use ($row, $unit, $status) {
            $this->setReturn($row, $unit, $status === 'pending' ? null : $status);
        });
        $text = $status === 'pending' ? 'pendiente de volver' : mb_strtolower(OrderUnit::RETURN_LABELS[$status]);

        return $this->respond($order, $mode, 'ok', "{$label}: {$text}.");
    }

    /** Termina la salida o la entrada y cambia el estado del pedido. */
    public function finish(Order $order, string $mode)
    {
        $this->authorizeMode($order, $mode);
        $state = $this->state($order);

        if ($mode === 'salida') {
            if ($order->status === OrderStatus::Paid) {
                $order->update(['status' => OrderStatus::Delivered]);
            }
            $missing = collect($state['expected'])->sum(fn ($e) => max(0, $e['needed'] - $e['scanned']));
            $msg = "Salida registrada: {$state['outCount']} unidades. Pedido marcado como entregado.";

            return redirect()->route('expeditions.show', $order)->with($missing ? 'error' : 'status', $missing ? $msg." Faltan {$missing} piezas por salir." : $msg);
        }

        if ($order->status === OrderStatus::Delivered) {
            $order->update(['status' => OrderStatus::Returned]);
        }
        $pending = $state['units']->filter->isOut()->count();
        $msg = 'Entrada registrada. Pedido marcado como devuelto.';

        return redirect()->route('expeditions.show', $order)->with($pending ? 'error' : 'status', $pending ? $msg." Quedan {$pending} unidades sin volver." : $msg);
    }

    private function checkOut(Order $order, Unit $unit, ?OrderUnit $existing, string $label): array
    {
        if ($existing) {
            return ['warn', "{$label} ya estaba escaneada en esta salida."];
        }
        if ($unit->status === UnitStatus::Rented) {
            $other = OrderUnit::where('unit_id', $unit->id)->whereNull('return_status')->with('order')->first()?->order;

            return ['error', "{$label} figura fuera con otro pedido".($other ? " ({$other->number})" : '').'. Dale entrada antes.'];
        }
        if (in_array($unit->status, [UnitStatus::Maintenance, UnitStatus::Retired], true)) {
            return ['error', "{$label} está «{$unit->status->label()}» y no se puede sacar."];
        }

        OrderUnit::create(['order_id' => $order->id, 'unit_id' => $unit->id, 'out_at' => now(), 'out_by' => auth()->id()]);
        $unit->update(['status' => UnitStatus::Rented]);

        $expected = Availability::expand($order->lines()->get());
        $needed = $expected[$unit->article_id] ?? 0;
        $scanned = OrderUnit::where('order_id', $order->id)->whereHas('unit', fn ($q) => $q->where('article_id', $unit->article_id))->count();
        if (! $needed) {
            return ['warn', "{$label}: añadida, pero no está en el pedido."];
        }
        if ($scanned > $needed) {
            return ['warn', "{$label}: añadida, pero sobra (el pedido lleva {$needed})."];
        }

        return ['ok', "{$label} ✓ ({$scanned} de {$needed})"];
    }

    private function checkIn(Unit $unit, ?OrderUnit $existing, string $label): array
    {
        if (! $existing) {
            return ['error', "{$label} no salió con este pedido."];
        }
        if (! $existing->isOut()) {
            return ['warn', "{$label} ya tenía entrada (".mb_strtolower(OrderUnit::RETURN_LABELS[$existing->return_status]).').'];
        }
        $this->setReturn($existing, $unit, 'ok');

        return ['ok', "{$label} ✓ devuelta"];
    }

    /** Estado de vuelta de una unidad y su efecto en el stock. null = sigue fuera. */
    private function setReturn(OrderUnit $row, Unit $unit, ?string $status): void
    {
        $row->update([
            'return_status' => $status,
            'in_at' => $status ? now() : null,
            'in_by' => $status ? auth()->id() : null,
        ]);
        $unit->update(['status' => match ($status) {
            'ok' => UnitStatus::Available,
            'damaged' => UnitStatus::Maintenance,
            'lost' => UnitStatus::Retired,
            null => UnitStatus::Rented,
        }]);
    }

    /** Busca la unidad por la URL del QR, por su token o por el código impreso (SIL-0001-003). */
    private function findUnit(string $code): ?Unit
    {
        $token = Str::contains($code, '/q/') ? Str::afterLast(rtrim(strtok($code, '?#'), '/'), '/q/') : $code;

        return Unit::with('article')->where('qr_token', $token)->first()
            ?? Unit::with('article')->where('code', strtoupper($code))->first();
    }

    private function authorizeMode(Order $order, string $mode): void
    {
        abort_unless(isset(self::MODES[$mode]), 404);
        $allowed = $mode === 'salida'
            ? [OrderStatus::Paid, OrderStatus::Delivered]
            : [OrderStatus::Delivered, OrderStatus::Returned];
        abort_unless(in_array($order->status, $allowed, true), 403,
            $mode === 'salida' ? 'Solo se puede dar salida a un pedido pagado.' : 'Solo se puede dar entrada a un pedido entregado.');
    }

    /** Lo que lleva el pedido (en piezas físicas) frente a lo escaneado. */
    private function state(Order $order): array
    {
        $order->load('lines', 'client');
        $units = $order->units()->with('unit.article')->get();
        $expected = Availability::expand($order->lines);
        $scanned = $units->countBy(fn ($u) => $u->unit->article_id);
        $ids = array_unique(array_merge(array_keys($expected), $scanned->keys()->all()));
        $articles = Article::withTrashed()->with('mainPhoto')->whereIn('id', $ids)->orderBy('reference')->get();

        return [
            'units' => $units,
            'outCount' => $units->count(),
            'expected' => $articles->map(fn (Article $a) => [
                'article' => $a,
                'needed' => $expected[$a->id] ?? 0,
                'scanned' => $scanned[$a->id] ?? 0,
                'returned' => $units->filter(fn ($u) => $u->unit->article_id === $a->id && ! $u->isOut())->count(),
            ])->all(),
        ];
    }

    private function respond(Order $order, string $mode, string $level, string $message): JsonResponse
    {
        return response()->json([
            'level' => $level,
            'message' => $message,
            'html' => view('scan._list', ['order' => $order, 'mode' => $mode] + $this->state($order))->render(),
        ]);
    }
}
