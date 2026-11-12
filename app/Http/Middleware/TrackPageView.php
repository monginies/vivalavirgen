<?php

namespace App\Http\Middleware;

use App\Models\Article;
use App\Models\Category;
use App\Support\Tracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Cuenta las páginas vistas de la web de clientes. */
class TrackPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && ! $request->ajax() && ! $request->expectsJson()
            && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $route = $request->route();
            $data = ['path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255)];
            if ($route?->getName() === 'portal.article') {
                $data['article_id'] = Article::where('reference', $route->parameter('reference'))->value('id');
            } elseif ($route?->getName() === 'portal.collection') {
                $data['category_id'] = Category::findBySlug($route->parameter('slug'))?->id;
            }
            Tracker::record('view', $data, $request);
        }

        return $response;
    }
}
