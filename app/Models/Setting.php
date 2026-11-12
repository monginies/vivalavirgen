<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * Ajustes editables desde la app. Los valores por defecto son provisionales
     * hasta que se fijen las cifras definitivas (ver decisiones.md).
     */
    public const DEFINITIONS = [
        'transport_price_per_km' => ['label' => 'Transporte: precio por km', 'unit' => '€/km', 'default' => '0.60', 'type' => 'number',
            'hint' => 'Se aplica a 2 trayectos (ida y vuelta) × km × nº de camiones.'],
        'transport_minimum' => ['label' => 'Transporte: importe mínimo', 'unit' => '€', 'default' => '60', 'type' => 'number',
            'hint' => 'Si el cálculo sale por debajo, se cobra este importe.'],
        'deposit_amount' => ['label' => 'Fianza por pedido', 'unit' => '€', 'default' => '150', 'type' => 'number',
            'hint' => 'Importe fijo. Se devuelve al revisar el material, descontando roturas o pérdidas.'],
        'prereservation_days' => ['label' => 'Duración de la pre-reserva', 'unit' => 'días', 'default' => '3', 'type' => 'number',
            'hint' => 'Si el pedido no se paga en este plazo, caduca y el material se libera.'],
        'vat_rate' => ['label' => 'IVA', 'unit' => '%', 'default' => '21', 'type' => 'number', 'hint' => null],
        'company_name' => ['label' => 'Proforma: titular / razón social', 'unit' => null, 'default' => null, 'type' => 'text', 'hint' => null],
        'company_tax_id' => ['label' => 'Proforma: CIF / NIF', 'unit' => null, 'default' => null, 'type' => 'text', 'hint' => null],
        'company_address' => ['label' => 'Proforma: dirección fiscal', 'unit' => null, 'default' => 'C/ Driza, 13 · 29006 Málaga', 'type' => 'text', 'hint' => null],
        'company_phone' => ['label' => 'Proforma: teléfono', 'unit' => null, 'default' => null, 'type' => 'text', 'hint' => null],
        'company_email' => ['label' => 'Proforma: email', 'unit' => null, 'default' => 'gestion@vivalavirgen.net', 'type' => 'text', 'hint' => null],
        'company_iban' => ['label' => 'Proforma: IBAN para transferencias', 'unit' => null, 'default' => null, 'type' => 'text', 'hint' => 'Si lo dejas vacío, la proforma no muestra el bloque de pago.'],
        'proforma_terms' => ['label' => 'Proforma: condiciones', 'unit' => null, 'type' => 'textarea', 'hint' => 'Texto al pie de la proforma (pago, roturas, cancelaciones…).',
            'default' => "El material queda reservado hasta la fecha de validez indicada. El pedido se confirma con el pago.\nLa fianza se devuelve tras revisar el material a su regreso. Las piezas dañadas o no devueltas se facturan a su precio de reposición.\n[Texto provisional: revisar condiciones de pago y cancelación]"],
        'hero_title' => ['label' => 'Web: frase de portada', 'unit' => null, 'default' => 'Momentos que se quedan', 'type' => 'text', 'hint' => 'Texto grande sobre la foto de la portada.'],
        'hero_image' => ['label' => 'Web: foto de portada', 'unit' => null, 'default' => null, 'type' => 'image',
            'hint' => 'Foto horizontal de un montaje, lo más grande posible (se reduce a 2400 px).'],
        'google_site_verification' => ['label' => 'Google Search Console: código de verificación', 'unit' => null, 'default' => null, 'type' => 'text',
            'hint' => 'El valor content="…" de la etiqueta HTML que da Search Console al añadir la web.'],
        'notify_email' => ['label' => 'Email para avisos de la web', 'unit' => null, 'default' => 'gestion@vivalavirgen.net', 'type' => 'text',
            'hint' => 'Aquí llegan los avisos de nuevos registros y pedidos hechos por los clientes.'],
        'base_address' => ['label' => 'Dirección de la base', 'unit' => null, 'default' => 'Calle Driza, 13, 29006 Málaga', 'type' => 'text',
            'hint' => 'Punto de salida para calcular los km.'],
    ];

    private static ?array $cache = null;

    public static function get(string $key): ?string
    {
        self::$cache ??= static::query()->pluck('value', 'key')->all();

        return self::$cache[$key] ?? self::DEFINITIONS[$key]['default'] ?? null;
    }

    public static function number(string $key): float
    {
        return (float) static::get($key);
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache = null;
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
