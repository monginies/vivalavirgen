<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __invoke(Request $request)
    {
        Order::expireOverdue();

        $month = rescue(fn () => CarbonImmutable::createFromFormat('Y-m', (string) $request->month)->startOfMonth(), null, false)
            ?? CarbonImmutable::today()->startOfMonth();
        $gridStart = $month->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $month->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        $orders = Order::query()
            ->with('client')
            ->whereIn('status', array_merge(OrderStatus::blocking(), [OrderStatus::Closed]))
            ->where('start_date', '<=', $gridEnd->toDateString())
            ->where('end_date', '>=', $gridStart->toDateString())
            ->orderBy('start_date')
            ->get();

        $days = [];
        foreach (CarbonPeriod::create($gridStart, $gridEnd) as $day) {
            $d = $day->toDateString();
            $days[$d] = [
                'date' => CarbonImmutable::parse($day),
                'orders' => $orders->filter(fn ($o) => $o->start_date->toDateString() <= $d && $o->end_date->toDateString() >= $d),
            ];
        }

        return view('calendar', [
            'month' => $month,
            'weeks' => array_chunk($days, 7, true),
        ]);
    }
}
