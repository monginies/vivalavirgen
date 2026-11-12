<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Distancia por carretera desde la base hasta un destino, con OpenRouteService.
 * Geocodifica la dirección (Pelias) y pide la ruta en coche.
 * La clave va en .env (ORS_API_KEY). Las respuestas se guardan en caché 30 días.
 */
class Distance
{
    private const BASE_URL = 'https://api.openrouteservice.org';

    public static function configured(): bool
    {
        return filled(config('services.openrouteservice.key'));
    }

    /**
     * @return array{km: float, label: string}
     *
     * @throws RuntimeException con un mensaje para mostrar al usuario
     */
    public static function fromBase(string $destination): array
    {
        if (! self::configured()) {
            throw new RuntimeException('Falta la clave de OpenRouteService en el servidor.');
        }

        $origin = self::geocode(Setting::get('base_address'));
        if (! $origin) {
            throw new RuntimeException('No se encuentra la dirección de la base. Revísala en Ajustes.');
        }
        $target = self::geocode($destination);
        if (! $target) {
            throw new RuntimeException('No se encuentra esa dirección. Prueba a añadir la población o el código postal.');
        }

        $km = Cache::remember(
            'ors:route:'.md5(json_encode([$origin['coords'], $target['coords']])),
            now()->addDays(30),
            fn () => self::drivingKm($origin['coords'], $target['coords'])
        );

        return ['km' => round($km, 1), 'label' => $target['label']];
    }

    /** @return array{coords: array{0: float, 1: float}, label: string}|null */
    private static function geocode(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        return Cache::remember('ors:geo:'.md5(mb_strtolower($text)), now()->addDays(30), function () use ($text) {
            $res = Http::timeout(10)->get(self::BASE_URL.'/geocode/search', [
                'api_key' => config('services.openrouteservice.key'),
                'text' => $text,
                'boundary.country' => 'ES',
                'size' => 1,
            ]);
            self::failIfError($res);
            $f = $res->json('features.0');

            return $f ? ['coords' => $f['geometry']['coordinates'], 'label' => $f['properties']['label'] ?? $text] : null;
        });
    }

    private static function drivingKm(array $from, array $to): float
    {
        $res = Http::timeout(15)
            ->withHeaders(['Authorization' => config('services.openrouteservice.key')])
            ->post(self::BASE_URL.'/v2/directions/driving-car', ['coordinates' => [$from, $to]]);
        self::failIfError($res);

        $meters = $res->json('routes.0.summary.distance');
        if ($meters === null) {
            throw new RuntimeException('No se ha podido calcular una ruta en coche hasta ese destino.');
        }

        return $meters / 1000;
    }

    private static function failIfError($res): void
    {
        if ($res->successful()) {
            return;
        }
        throw new RuntimeException(match ($res->status()) {
            401, 403 => 'La clave de OpenRouteService no es válida.',
            429 => 'Se ha superado el límite diario de cálculos. Introduce los km a mano.',
            default => 'El servicio de mapas no responde ahora mismo. Introduce los km a mano.',
        });
    }
}
