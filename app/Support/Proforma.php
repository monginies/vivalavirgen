<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

/** PDF de la proforma de un pedido. */
class Proforma
{
    public static function filename(Order $order): string
    {
        return "Proforma-{$order->number}.pdf";
    }

    public static function pdf(Order $order): \Barryvdh\DomPDF\PDF
    {
        $order->loadMissing('client', 'lines.article', 'venue');

        return self::render($order);
    }

    /**
     * Proforma que el profesional da a su cliente: precios de tarifa (sin su
     * descuento / comisión ni retención), a nombre del cliente final y con
     * «Pedido gestionado por» el profesional. Se paga igual a Viva la Virgen.
     *
     * @param  array{name: string, email?: ?string, tax_id?: ?string, address?: ?string}  $endClient
     */
    public static function forEndClient(Order $order, array $endClient): \Barryvdh\DomPDF\PDF
    {
        $order->loadMissing('client', 'lines.article', 'venue');

        return self::render(self::atListPrice($order), $endClient);
    }

    /** Copia del pedido (sin guardar) sin descuento ni retención, para calcular los importes de tarifa. */
    public static function atListPrice(Order $order): Order
    {
        $copy = clone $order;
        $copy->discount_percent = 0;
        $copy->withholding_rate = 0;

        return $copy;
    }

    private static function render(Order $order, ?array $endClient = null): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.proforma', [
            'order' => $order,
            'endClient' => $endClient,
            'company' => collect(['name', 'tax_id', 'address', 'phone', 'email', 'iban'])
                ->mapWithKeys(fn ($k) => [$k => Setting::get('company_'.$k)])->all(),
            'terms' => Setting::get('proforma_terms'),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true);
    }
}
