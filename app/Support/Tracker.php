<?php

namespace App\Support;

use App\Models\WebEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Estadísticas propias de la web de clientes.
 * No guarda IP ni usa cookies propias: identifica la visita con un valor aleatorio
 * dentro de la sesión, que ya existe para el pedido.
 */
class Tracker
{
    private const BOTS = '/bot|crawl|spider|slurp|scrap|facebookexternalhit|whatsapp|telegram|preview|monitor|uptime|pingdom|lighthouse|headless|curl|wget|python|java\/|go-http|okhttp|axios|node-fetch|php\//i';

    /** Procedencias conocidas: fragmento del dominio => nombre. */
    private const SOURCES = [
        'google.' => 'Google', 'bing.' => 'Bing', 'duckduckgo.' => 'DuckDuckGo', 'yahoo.' => 'Yahoo', 'ecosia.' => 'Ecosia',
        'instagram.' => 'Instagram', 'facebook.' => 'Facebook', 'fb.' => 'Facebook', 'pinterest.' => 'Pinterest',
        'tiktok.' => 'TikTok', 'linkedin.' => 'LinkedIn', 'whatsapp.' => 'WhatsApp', 't.co' => 'X (Twitter)',
        'bodas.net' => 'Bodas.net', 'zankyou.' => 'Zankyou', 'chatgpt.' => 'ChatGPT', 'openai.' => 'ChatGPT',
        'claude.ai' => 'Claude', 'perplexity.' => 'Perplexity', 'gemini.google' => 'Gemini',
    ];

    public static function record(string $type, array $data = [], ?Request $request = null): void
    {
        $request ??= request();
        if (! self::shouldTrack($request)) {
            return;
        }

        try {
            WebEvent::create($data + [
                'type' => $type,
                'visitor' => self::visitor($request),
                'client_id' => Auth::guard('client')->id(),
                'source' => self::source($request),
                'device' => self::device((string) $request->userAgent()),
            ]);
        } catch (\Throwable $e) {
            report($e); // las estadísticas nunca deben romper la web
        }
    }

    public static function shouldTrack(Request $request): bool
    {
        $ua = (string) $request->userAgent();

        return $request->hasSession()
            && $ua !== '' && ! preg_match(self::BOTS, $ua)
            && ! Auth::guard('web')->check(); // el equipo interno no cuenta
    }

    private static function visitor(Request $request): string
    {
        $session = $request->session();
        if (! $session->has('vt.id')) {
            $session->put('vt.id', Str::lower(Str::random(16)));
        }

        return $session->get('vt.id');
    }

    /** La procedencia se fija en la primera página de la visita. */
    private static function source(Request $request): string
    {
        $session = $request->session();
        if ($session->has('vt.src')) {
            return $session->get('vt.src');
        }

        $source = 'Directo';
        if ($utm = $request->query('utm_source')) {
            $source = self::label(Str::lower((string) $utm)) ?? Str::limit(Str::lower((string) $utm), 50, '');
        } elseif ($ref = $request->headers->get('referer')) {
            $host = Str::lower((string) parse_url($ref, PHP_URL_HOST));
            if ($host && $host !== Str::lower($request->getHost())) {
                $source = self::label($host) ?? Str::limit(preg_replace('/^(www|m|l|lm)\./', '', $host), 50, '');
            }
        }
        $session->put('vt.src', $source);

        return $source;
    }

    private static function label(string $host): ?string
    {
        foreach (self::SOURCES as $needle => $name) {
            if (str_contains($host.'.', $needle) || $host === rtrim($needle, '.')) {
                return $name;
            }
        }

        return null;
    }

    private static function device(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet|kindle|silk|(android(?!.*mobile))/i', $ua) => 'tablet',
            (bool) preg_match('/mobi|iphone|ipod|android|phone/i', $ua) => 'movil',
            default => 'ordenador',
        };
    }
}
