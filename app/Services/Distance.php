<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Distancia por carretera desde la base hasta un destino, con OpenRouteService.
 * Geocodifica la dirección (Pelias) y pide la ruta en coche.
 * La clave va en .env (ORS_API_KEY). Las respuestas se guardan en caché 30 días.
 * El destino se busca en un radio de SEARCH_RADIUS_KM alrededor de la base, para no
 * confundir nombres repetidos (p. ej. otra «Finca La Concepción» en Canarias).
 */
class Distance
{
    private const BASE_URL = 'https://api.openrouteservice.org';

    private const SEARCH_RADIUS_KM = 300;

    public static function configured(): bool
    {
        return filled(config('services.openrouteservice.key'));
    }

    /**
     * @return array{km: string, label: string}
     *
     * @throws RuntimeException con un mensaje para mostrar al usuario
     */
    public static function fromBase(?string $address, ?string $city = null): array
    {
        if (! self::configured()) {
            throw new RuntimeException('Falta la clave de OpenRouteService en el servidor.');
        }

        $origin = self::geocode(Setting::get('base_address'));
        if (! $origin) {
            throw new RuntimeException('No se encuentra la dirección de la base. Revísala en Ajustes.');
        }
        $target = self::geocode((string) $address, $origin['coords'], $city);
        if (! $target) {
            throw new RuntimeException('No se encuentra esa dirección a menos de '.self::SEARCH_RADIUS_KM.' km de la base. Prueba a añadir la población o el código postal.');
        }

        $km = Cache::remember(
            'ors:route:'.md5(json_encode([$origin['coords'], $target['coords']])),
            now()->addDays(30),
            fn () => self::drivingKm($origin['coords'], $target['coords'])
        );

        return ['km' => number_format($km, 1, '.', ''), 'label' => $target['label']];
    }

    /** @return array{coords: array{0: float, 1: float}, label: string}|null */
    /**
     * Busca un punto. Con población:
     *  1) búsqueda libre «dirección, población» cerca de la base; se acepta solo si el
     *     resultado es una dirección/lugar concreto y está en esa población;
     *  2) si no, búsqueda estructurada, que como mínimo devuelve el centro de la población
     *     (mejor que un sitio con el mismo nombre en otro pueblo).
     */
    private static function geocode(string $text, ?array $near = null, ?string $city = null): ?array
    {
        $text = self::normalize($text);
        $city = trim((string) $city);
        if ($city === '' && $near && str_contains($text, ',')) {
            // «Finca X, Ronda»: la última parte se toma como población
            $city = trim(Str::afterLast($text, ','));
            $text = trim(Str::beforeLast($text, ','));
        }
        if ($text === '' && $city === '') {
            return null;
        }

        $cacheKey = 'ors:geo2:'.md5(mb_strtolower($text.'|'.$city).json_encode($near));

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($text, $city, $near) {
            $area = $near ? [
                'focus.point.lon' => $near[0], 'focus.point.lat' => $near[1],
                'boundary.circle.lon' => $near[0], 'boundary.circle.lat' => $near[1],
                'boundary.circle.radius' => self::SEARCH_RADIUS_KM,
            ] : [];

            if ($text !== '') {
                $f = self::search('/geocode/search', ['text' => $city !== '' ? "{$text}, {$city}" : $text] + $area);
                $precise = $f && ! in_array($f['properties']['layer'] ?? null, self::AREA_LAYERS, true);
                if ($f && $precise && self::sameName($f, $text) && ($city === '' || self::sameCity($f, $city))) {
                    return self::point($f, $text);
                }
            }
            if ($city === '') {
                return null;
            }

            $f = self::search('/geocode/search/structured', array_filter(['address' => $text, 'locality' => $city, 'country' => 'ES']) + $area);

            return $f ? self::point($f, $text) : null;
        });
    }

    private const AREA_LAYERS = ['locality', 'localadmin', 'county', 'region', 'macroregion', 'borough', 'neighbourhood'];

    private static function search(string $path, array $query): ?array
    {
        $res = Http::timeout(10)->get(self::BASE_URL.$path, $query + [
            'api_key' => config('services.openrouteservice.key'),
            'boundary.country' => 'ES',
            'size' => 1,
        ]);
        self::failIfError($res);

        return $res->json('features.0');
    }

    private static function point(array $f, string $text): array
    {
        $label = $f['properties']['label'] ?? $text;
        if ($text !== '' && in_array($f['properties']['layer'] ?? null, self::AREA_LAYERS, true)) {
            $label .= ' (no se ha encontrado la dirección exacta; se toma el centro de la población)';
        }

        return ['coords' => $f['geometry']['coordinates'], 'label' => $label];
    }

    private static function sameCity(array $f, string $city): bool
    {
        $norm = fn ($v) => Str::lower(Str::ascii((string) $v));
        $p = $f['properties'];
        $haystack = $norm(implode(' ', [$p['locality'] ?? '', $p['localadmin'] ?? '', $p['county'] ?? '', $p['label'] ?? '']));

        return str_contains($haystack, $norm($city));
    }

    /** Las palabras propias de la dirección (no «calle», «finca», números…) deben aparecer en el resultado. */
    private static function sameName(array $f, string $text): bool
    {
        $words = fn ($v) => preg_split('~[^a-z0-9]+~', Str::lower(Str::ascii((string) $v)), -1, PREG_SPLIT_NO_EMPTY);
        $generic = ['calle', 'avenida', 'carretera', 'plaza', 'camino', 'paseo', 'finca', 'hacienda', 'cortijo',
            'urbanizacion', 'poligono', 'salon', 'hotel', 'restaurante', 'la', 'el', 'los', 'las', 'de', 'del', 'y', 'en', 'km', 's', 'n'];
        $wanted = array_filter($words($text), fn ($w) => strlen($w) >= 3 && ! ctype_digit($w) && ! in_array($w, $generic, true));
        $found = $words(($f['properties']['name'] ?? '').' '.($f['properties']['label'] ?? ''));

        return ! array_diff($wanted, $found);
    }

    /** Desarrolla abreviaturas que el buscador no entiende (C/, Avda., Ctra.…). */
    private static function normalize(string $text): string
    {
        $text = preg_replace(
            ['~\bC/\s*~iu', '~\bAvda?\.?\s+~iu', '~\bCtra\.?\s+~iu', '~\bPza\.?\s+~iu', '~\bUrb\.?\s+~iu'],
            ['Calle ', 'Avenida ', 'Carretera ', 'Plaza ', 'Urbanización '],
            trim($text)
        );

        return trim(preg_replace('~\s+~', ' ', $text));
    }

    private static function drivingKm(array $from, array $to): float
    {
        $res = Http::timeout(15)
            ->withHeaders(['Authorization' => config('services.openrouteservice.key')])
            ->post(self::BASE_URL.'/v2/directions/driving-car', ['coordinates' => [$from, $to]]);
        if ($res->status() === 404 || $res->json('error.code') === 2010) {
            throw new RuntimeException('No hay ruta en coche hasta ese punto. Revisa la dirección o escribe los km a mano.');
        }
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
