<?php

namespace App\Support;

use App\Models\Setting;

/** Datos estructurados (schema.org) para que Google entienda las páginas de la web. */
class JsonLd
{
    public static function script(array $data): string
    {
        $json = json_encode(['@context' => 'https://schema.org'] + $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return '<script type="application/ld+json">'.$json.'</script>';
    }

    /** El negocio: nombre, dirección, contacto y zona de servicio. */
    public static function business(): array
    {
        $phone = preg_replace('/\\D+/', '', (string) Setting::get('company_phone'));
        $hero = Setting::get('hero_image');

        return array_filter([
            '@type' => 'LocalBusiness',
            '@id' => url('/').'#negocio',
            'name' => config('app.name'),
            'description' => 'Alquiler de mobiliario y decoración mediterránea para bodas y eventos en Málaga y provincia.',
            'url' => url('/'),
            'logo' => asset('img/logo-320.png'),
            'image' => $hero ? asset('storage/'.$hero) : asset('img/logo-320.png'),
            'email' => Setting::get('company_email'),
            'telephone' => $phone ? '+34'.$phone : null,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'C/ Driza, 13',
                'postalCode' => '29006',
                'addressLocality' => 'Málaga',
                'addressRegion' => 'Málaga',
                'addressCountry' => 'ES',
            ],
            'areaServed' => ['@type' => 'AdministrativeArea', 'name' => 'Provincia de Málaga'],
        ]);
    }

    public static function breadcrumbs(array $items): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0], 'item' => $item[1],
            ])->all(),
        ];
    }
}
