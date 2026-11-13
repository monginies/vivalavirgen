<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * App web instalable (PWA): manifiestos de la web de clientes y del backoffice,
 * service worker (página sin conexión y notificaciones push) y página sin conexión.
 */
class PwaController extends Controller
{
    public function portalManifest()
    {
        return $this->manifest([
            'name' => config('app.name'),
            'short_name' => 'Viva la Virgen',
            'description' => 'Alquiler de mobiliario y decoración mediterránea para bodas y eventos en Málaga.',
            'start_url' => '/?source=app',
            'scope' => '/',
            'id' => '/',
        ]);
    }

    public function appManifest()
    {
        return $this->manifest([
            'name' => 'Viva la Virgen · Gestión',
            'short_name' => 'VLV Gestión',
            'description' => 'Pedidos, expediciones y escaneo de material de Viva la Virgen.',
            'start_url' => '/app/',
            'scope' => '/app/',
            'id' => '/app/',
        ]);
    }

    public function serviceWorker(): Response
    {
        return response(view('pwa.sw')->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    public function offline()
    {
        return view('pwa.offline');
    }

    private function manifest(array $data)
    {
        return response()->json($data + [
            'lang' => 'es',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#fcfbf6',
            'theme_color' => '#9a9b88',
            'icons' => [
                ['src' => asset('img/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('img/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('img/icon-maskable-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => asset('img/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
