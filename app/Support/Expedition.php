<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/** Hoja de expedición de un pedido: documento de logística, sin precios. */
class Expedition
{
    public static function filename(Order $order): string
    {
        return "Expedicion-{$order->number}.pdf";
    }

    /**
     * Filas de material: cada línea del pedido y, si es un combinado, sus piezas
     * debajo (que son lo que se carga).
     *
     * @return array<int, array{article: Article, quantity: int, bundle: bool, part: bool}>
     */
    public static function rows(Order $order): array
    {
        $order->loadMissing('lines.article.components.mainPhoto', 'lines.article.mainPhoto');
        $rows = [];
        foreach ($order->lines->sortBy('id') as $line) {
            $a = $line->article;
            $rows[] = ['article' => $a, 'quantity' => $line->quantity, 'bundle' => $a->is_bundle, 'part' => false];
            if ($a->is_bundle) {
                foreach ($a->components as $c) {
                    $rows[] = ['article' => $c, 'quantity' => $line->quantity * $c->pivot->quantity, 'bundle' => false, 'part' => true];
                }
            }
        }

        return $rows;
    }

    /** Ruta local de la miniatura (dompdf la lee del disco). */
    public static function thumb(Article $article): ?string
    {
        $path = $article->mainPhoto?->thumb_path;

        return $path && Storage::disk('public')->exists($path) ? Storage::disk('public')->path($path) : null;
    }

    public static function pdf(Order $order): \Barryvdh\DomPDF\PDF
    {
        $order->loadMissing('client', 'venue', 'payer');

        return Pdf::loadView('pdf.expedition', [
            'order' => $order,
            'rows' => self::rows($order),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)
            ->setOption('chroot', [public_path(), storage_path('app/public')]);
    }
}
