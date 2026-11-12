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

        return Pdf::loadView('pdf.proforma', [
            'order' => $order,
            'company' => collect(['name', 'tax_id', 'address', 'phone', 'email', 'iban'])
                ->mapWithKeys(fn ($k) => [$k => Setting::get('company_'.$k)])->all(),
            'terms' => Setting::get('proforma_terms'),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true);
    }
}
