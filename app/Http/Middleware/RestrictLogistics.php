<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Los usuarios de logística solo pueden usar las expediciones (lista, hojas y escaneo). */
class RestrictLogistics
{
    public const ALLOWED = ['logout', 'dashboard', 'expeditions.*', 'orders.expedition', 'scan.*', 'qr.resolve'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isLogistics() && ! $request->routeIs(...self::ALLOWED)) {
            abort(403, 'Tu usuario de logística solo tiene acceso a las expediciones.');
        }

        return $next($request);
    }
}
