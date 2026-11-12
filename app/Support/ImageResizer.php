<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Guarda una foto subida en dos tamaños (grande y miniatura) como JPEG, con GD. */
class ImageResizer
{
    public const LARGE = 1600;

    public const THUMB = 480;

    /** @return array{path: string, thumb_path: string} */
    public static function store(UploadedFile $file, string $dir, int $large = self::LARGE): array
    {
        $data = @file_get_contents($file->getRealPath());
        $src = $data !== false ? @imagecreatefromstring($data) : false;
        if (! $src) {
            throw new RuntimeException("No se puede leer la imagen «{$file->getClientOriginalName()}». Usa JPG, PNG o WebP.");
        }

        $name = $dir.'/'.Str::uuid();
        $paths = ['path' => "{$name}.jpg", 'thumb_path' => "{$name}-t.jpg"];
        Storage::disk('public')->put($paths['path'], self::jpeg($src, $large, 82));
        Storage::disk('public')->put($paths['thumb_path'], self::jpeg($src, self::THUMB, 78));
        imagedestroy($src);

        return $paths;
    }

    private static function jpeg(\GdImage $src, int $max, int $quality): string
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // fondo blanco para PNG transparentes
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($dst, null, $quality);
        imagedestroy($dst);

        return ob_get_clean();
    }
}
