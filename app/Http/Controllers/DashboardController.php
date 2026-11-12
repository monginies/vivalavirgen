<?php

namespace App\Http\Controllers;

use App\Enums\UnitStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\Unit;

class DashboardController extends Controller
{
    public function __invoke()
    {
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
            'recentArticles' => Article::with('category')->withCount('units')->latest()->take(5)->get(),
        ]);
    }
}
