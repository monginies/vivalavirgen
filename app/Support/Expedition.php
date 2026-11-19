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
     * Filas de material, lo que se carga: las líneas sueltas y, en lugar de cada
     * combinado, sus piezas (con `from` = de qué combinado vienen). Ordenadas por
     * localizador para recorrer el almacén en orden; sin localizador, al final.
     *
     * @return array<int, array{article: Article, quantity: int, part: bool, from: ?string}>
     */
    public static function rows(Order $order): array
    {
        $order->loadMissing('lines.article.components.mainPhoto', 'lines.article.mainPhoto');
        $rows = [];
        foreach ($order->lines->sortBy('id') as $line) {
            $a = $line->article;
            if (! $a->is_bundle) {
                $rows[] = ['article' => $a, 'quantity' => $line->quantity, 'part' => false, 'from' => null];

                continue;
            }
            foreach ($a->components as $c) {
                $rows[] = ['article' => $c, 'quantity' => $line->quantity * $c->pivot->quantity, 'part' => true, 'from' => "{$line->quantity} × {$a->name}"];
            }
        }

        // usort es estable: a igual localizador se mantiene el orden del pedido
        usort($rows, fn ($x, $y) => self::compareLocation($x['article']->location, $y['article']->location));

        return $rows;
    }

    /** Orden natural por la primera balda (1-A, 1-B, 2-A… 10-A); vacíos al final. */
    public static function compareLocation(?string $a, ?string $b): int
    {
        $a = trim(explode(',', (string) $a)[0]);
        $b = trim(explode(',', (string) $b)[0]);
        if ($a === '' || $b === '') {
            return ($a === '') <=> ($b === '');
        }

        return strnatcasecmp($a, $b);
    }

    /** Miniatura en escala de grises (la hoja se imprime en blanco y negro). */
    public static function thumb(Article $article): ?string
    {
        $path = $article->mainPhoto?->thumb_path;

        return $path && Storage::disk('public')->exists($path) ? self::gray(Storage::disk('public')->path($path)) : null;
    }

    /** Imagen reducida y en escala de grises, como data URI PNG; null si no se puede leer. */
    public static function gray(string $file, int $max = 120): ?string
    {
        $data = @file_get_contents($file);
        $src = $data !== false ? @imagecreatefromstring($data) : false;
        if (! $src) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        imagefilter($dst, IMG_FILTER_GRAYSCALE);
        ob_start();
        imagepng($dst);
        $png = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return 'data:image/png;base64,'.base64_encode($png);
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
