<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Category;
use App\Support\ImageResizer;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Importa artículos desde un CSV (separado por «;», UTF-8) con las columnas
 * categoria;prefijo;nombre;descripcion;medidas;precio_alquiler;coste_reposicion;pagina_catalogo;foto;revisar
 *
 * - La categoría se busca por su prefijo (`code`); si no existe, se crea.
 * - Si ya hay un artículo con el mismo nombre en la categoría, se salta (se puede relanzar sin duplicar).
 * - No crea unidades: se añaden al contar el stock real.
 */
class ImportCatalog extends Command
{
    protected $signature = 'catalog:import
        {csv : Ruta del CSV}
        {--photos= : Carpeta con las fotos indicadas en la columna «foto»}
        {--inactive : Crear los artículos desactivados (no salen en la web hasta activarlos)}
        {--dry-run : Solo mostrar lo que se haría}';

    protected $description = 'Importa artículos (y su foto principal) desde un CSV del catálogo';

    public function handle(): int
    {
        $path = $this->argument('csv');
        if (! is_readable($path)) {
            $this->error("No se puede leer {$path}");

            return self::FAILURE;
        }

        $fh = fopen($path, 'r');
        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), fgetcsv($fh, null, ';'));
        $required = ['categoria', 'prefijo', 'nombre', 'precio_alquiler'];
        if ($missing = array_diff($required, $header)) {
            $this->error('Faltan columnas: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $rows = [];
        while (($data = fgetcsv($fh, null, ';')) !== false) {
            if (count($data) === count($header)) {
                $rows[] = array_combine($header, $data);
            }
        }
        fclose($fh);

        $dir = $this->option('photos');
        $dry = $this->option('dry-run');
        $stats = ['categorias' => 0, 'articulos' => 0, 'fotos' => 0, 'saltados' => 0];

        foreach ($rows as $row) {
            $code = strtoupper(trim($row['prefijo']));
            $category = Category::where('code', $code)->first();
            if (! $category) {
                if (Category::where('name', trim($row['categoria']))->exists()) {
                    $this->error("La categoría «{$row['categoria']}» existe con otro prefijo distinto de {$code}");

                    return self::FAILURE;
                }
                $stats['categorias']++;
                if ($dry) {
                    $this->line("Nueva categoría: {$row['categoria']} ({$code})");
                    $category = new Category(['name' => $row['categoria'], 'code' => $code]);
                } else {
                    $category = Category::create(['name' => trim($row['categoria']), 'code' => $code, 'sort' => 999, 'visible' => true]);
                }
            }

            $name = trim($row['nombre']);
            if ($category->exists && $category->articles()->where('name', $name)->exists()) {
                $stats['saltados']++;

                continue;
            }

            $photo = trim($row['foto'] ?? '');
            $photoPath = $photo && $dir ? rtrim($dir, '/').'/'.$photo : null;
            if ($photoPath && ! is_readable($photoPath)) {
                $this->warn("Falta la foto {$photo} de «{$name}»");
                $photoPath = null;
            }

            $stats['articulos']++;
            $stats['fotos'] += $photoPath ? 1 : 0;
            if ($dry) {
                continue;
            }

            $notes = collect([
                ! empty($row['pagina_catalogo']) ? "Importado del catálogo 2026 (pág. {$row['pagina_catalogo']})." : null,
                ! empty($row['revisar']) ? 'Revisar: '.trim($row['revisar']).'.' : null,
            ])->filter()->implode("\n");

            DB::transaction(function () use ($category, $row, $name, $notes, $photoPath) {
                $article = Article::create([
                    'category_id' => $category->id,
                    'reference' => Article::nextReference($category),
                    'name' => $name,
                    'description' => trim($row['descripcion'] ?? '') ?: null,
                    'dimensions' => trim($row['medidas'] ?? '') ?: null,
                    'rental_price' => $this->money($row['precio_alquiler']),
                    'replacement_price' => $this->money($row['coste_reposicion'] ?? ''),
                    'notes' => $notes ?: null,
                    'active' => ! $this->option('inactive'),
                ]);

                if ($photoPath) {
                    $file = new UploadedFile($photoPath, basename($photoPath), 'image/jpeg', null, true);
                    $article->photos()->create(ImageResizer::store($file, 'articles/'.$article->id) + ['position' => 0]);
                }
            });
        }

        $this->info(($dry ? '[Simulación] ' : '')."Categorías nuevas: {$stats['categorias']} · Artículos: {$stats['articulos']} · Fotos: {$stats['fotos']} · Ya existían: {$stats['saltados']}");

        return self::SUCCESS;
    }

    private function money(string $value): float
    {
        $value = str_replace(['€', ' '], '', trim($value));
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return round((float) $value, 2);
    }
}
