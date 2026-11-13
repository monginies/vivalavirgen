<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\UnitStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\Unit;

class DashboardController extends Controller
{
    public function __invoke()
    {
        if (auth()->user()->isLogistics()) {
            return redirect()->route('expeditions.index');
        }

        Order::expireOverdue();

        $unitsByStatus = Unit::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard', [
            'articles' => Article::count(),
            'categories' => Category::count(),
            'clients' => Client::count(),
            'units' => $unitsByStatus->sum(),
            'unitsByStatus' => collect(UnitStatus::cases())
                ->mapWithKeys(fn ($s) => [$s->value => ['status' => $s, 'total' => $unitsByStatus[$s->value] ?? 0]]),
            'upcomingOrders' => Order::with('client')
                ->whereIn('status', OrderStatus::blocking())
                ->where('end_date', '>=', today())
                ->orderBy('start_date')->take(8)->get(),
            'pendingClients' => Client::whereNotNull('password')->whereNotNull('email_verified_at')->whereNull('approved_at')->count(),
            'pendingOrders' => Order::whereIn('status', OrderStatus::expirable())->count(),
            'toApprove' => Order::with('client', 'lines')->where('status', OrderStatus::Requested)->orderBy('created_at')->get(),
            'toSend' => Order::with('client', 'lines')->where('status', OrderStatus::Accepted)->orderBy('expires_at')->get(),
            'toPay' => Order::with('client', 'lines')->where('status', OrderStatus::ProformaSent)->orderBy('expires_at')->get(),
            'recentArticles' => Article::with('category')->withCount('units')->latest()->take(5)->get(),
        ]);
    }
}
