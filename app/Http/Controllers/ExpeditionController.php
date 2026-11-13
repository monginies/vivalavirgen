<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Expedition;
use Illuminate\Http\Request;

/** Expediciones: pedidos pagados pendientes de salir, fuera o devueltos. Sin precios. */
class ExpeditionController extends Controller
{
    public const STATUSES = [OrderStatus::Paid, OrderStatus::Delivered, OrderStatus::Returned];

    public function index(Request $request)
    {
        $orders = Order::query()
            ->whereIn('status', self::STATUSES)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->with(['client', 'venue'])
            ->withCount('units')
            ->orderByRaw("CASE status WHEN 'delivered' THEN 1 WHEN 'paid' THEN 0 ELSE 2 END")
            ->orderBy('start_date')
            ->get();

        return view('expeditions.index', compact('orders'));
    }

    public function show(Order $order)
    {
        abort_unless(in_array($order->status, [...self::STATUSES, OrderStatus::Closed], true), 404);
        $order->load('client', 'venue', 'units.unit.article');

        return view('expeditions.show', ['order' => $order, 'rows' => Expedition::rows($order)]);
    }
}
