<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Order;
use App\Services\Availability;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request)
    {
        Order::expireOverdue();

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = $request->date('from') ?? today()->next('Friday');
        $to = $request->date('to') ?? $from->copy()->addDays(2);

        $articles = Article::query()
            ->where('active', true)
            ->with('category')
            ->when($request->category, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->q, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")))
            ->orderBy('reference')
            ->get();

        return view('availability', [
            'from' => $from,
            'to' => $to,
            'articles' => $articles,
            'availability' => Availability::forArticles($articles->pluck('id')->all(), $from, $to),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
