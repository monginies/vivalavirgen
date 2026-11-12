<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\WebEvent;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Estadísticas de uso de la web de clientes. */
class StatsController extends Controller
{
    public const RANGES = [7 => '7 días', 30 => '30 días', 90 => '3 meses', 365 => '12 meses'];

    public function __invoke(Request $request)
    {
        $days = array_key_exists((int) $request->query('dias'), self::RANGES) ? (int) $request->query('dias') : 30;
        $from = now()->subDays($days - 1)->startOfDay();
        $prevFrom = $from->copy()->subDays($days);

        $in = fn (?string $type = null) => WebEvent::query()->where('created_at', '>=', $from)->when($type, fn ($q) => $q->where('type', $type));
        $visitors = fn ($q) => (int) $q->distinct()->count('visitor');

        // Resumen
        $summary = [
            'visitors' => $visitors($in('view')),
            'prevVisitors' => $visitors(WebEvent::where('type', 'view')->whereBetween('created_at', [$prevFrom, $from])),
            'pageviews' => $in('view')->count(),
            'registers' => $in('register')->count(),
            'orders' => Order::where('source', 'web')->where('created_at', '>=', $from)->count(),
        ];
        $summary['perVisit'] = $summary['visitors'] ? round($summary['pageviews'] / $summary['visitors'], 1) : 0;

        // Visitas por día (o por semana en rangos largos)
        $byDay = $in('view')->selectRaw('DATE(created_at) as d, COUNT(DISTINCT visitor) as n')->groupBy('d')->pluck('n', 'd');
        $series = [];
        foreach (CarbonPeriod::create($from, now()) as $date) {
            $key = $days > 90 ? $date->copy()->startOfWeek()->toDateString() : $date->toDateString();
            $series[$key] = ($series[$key] ?? 0) + (int) ($byDay[$date->toDateString()] ?? 0);
        }

        // Embudo: de la visita a la solicitud de pedido
        $funnel = [
            ['Visitas a la web', $summary['visitors']],
            ['Vieron algún artículo', $visitors($in('view')->whereNotNull('article_id'))],
            ['Añadieron algo al pedido', $visitors($in('cart_add'))],
            ['Solicitaron el pedido', $visitors($in('checkout'))],
        ];

        // Artículos más vistos, con cuántas veces se añadieron al pedido
        $adds = $in('cart_add')->selectRaw('article_id, COUNT(*) as n')->groupBy('article_id')->pluck('n', 'article_id');
        $topArticles = $in('view')->whereNotNull('article_id')
            ->selectRaw('article_id, COUNT(*) as views, COUNT(DISTINCT visitor) as people')
            ->groupBy('article_id')->orderByDesc('views')->limit(15)->get();
        $articles = Article::withTrashed()->whereIn('id', $topArticles->pluck('article_id')->merge($adds->keys()))->get()->keyBy('id');
        $topArticles->each(fn ($r) => $r->adds = (int) ($adds[$r->article_id] ?? 0));

        $topCategories = $in('view')->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(DISTINCT visitor) as people')->groupBy('category_id')->orderByDesc('people')->get();
        $categories = Category::whereIn('id', $topCategories->pluck('category_id'))->pluck('name', 'id');

        // Búsquedas
        $searches = $in('search')->selectRaw('term, COUNT(*) as n, MIN(value) as results')
            ->groupBy('term')->orderByDesc('n')->limit(20)->get();
        $noResults = $in('search')->where('value', 0)->selectRaw('term, COUNT(*) as n, MAX(created_at) as last')
            ->groupBy('term')->orderByDesc('n')->limit(20)->get();

        // Procedencia y dispositivo (por visita)
        $sources = $in('view')->selectRaw('source, COUNT(DISTINCT visitor) as n')->groupBy('source')->orderByDesc('n')->get();
        $devices = $in('view')->selectRaw('device, COUNT(DISTINCT visitor) as n')->groupBy('device')->orderByDesc('n')->pluck('n', 'device');

        // Clientes con artículos en el pedido que no llegaron a solicitarlo
        $lastCheckout = $in('checkout')->whereNotNull('client_id')->selectRaw('client_id, MAX(created_at) as at')->groupBy('client_id')->pluck('at', 'client_id');
        $abandoned = $in('cart_add')->whereNotNull('client_id')
            ->selectRaw('client_id, MAX(created_at) as at, COUNT(DISTINCT article_id) as items')
            ->groupBy('client_id')->orderByDesc('at')->get()
            ->filter(fn ($r) => ! isset($lastCheckout[$r->client_id]) || $lastCheckout[$r->client_id] < $r->at)
            ->filter(fn ($r) => Carbon::parse($r->at)->lt(now()->subHours(2)))
            ->values();
        $abandonedClients = Client::withTrashed()->whereIn('id', $abandoned->pluck('client_id'))->get()->keyBy('id');

        // Clientes aprobados que todavía no han hecho ningún pedido
        $idle = Client::whereNotNull('approved_at')->whereDoesntHave('orders')
            ->orderByDesc('approved_at')->limit(20)->get();

        return view('stats.index', compact(
            'days', 'from', 'summary', 'series', 'funnel', 'topArticles', 'articles', 'topCategories', 'categories',
            'searches', 'noResults', 'sources', 'devices', 'abandoned', 'abandonedClients', 'idle',
        ));
    }
}
