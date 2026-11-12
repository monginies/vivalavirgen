<?php

namespace App\Console\Commands;

use App\Models\PostalCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use ZipArchive;

class ImportPostalCodes extends Command
{
    protected $signature = 'postal-codes:import {file? : ES.txt de GeoNames; si no se indica, se descarga}';

    protected $description = 'Carga los códigos postales de España (GeoNames) para situar los destinos de los pedidos';

    public function handle(): int
    {
        $file = $this->argument('file') ?? $this->download();
        if (! $file || ! is_readable($file)) {
            $this->error('No se ha podido obtener el fichero de códigos postales.');

            return self::FAILURE;
        }

        $rows = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $c = explode("\t", $line);
            if (count($c) < 11 || $c[0] !== 'ES' || ! preg_match('~^\d{5}$~', $c[1])) {
                continue;
            }
            $rows[] = [
                'code' => $c[1], 'place' => $c[2], 'province' => $c[5] ?: null,
                'municipality' => $c[7] ?: null, 'lat' => (float) $c[9], 'lon' => (float) $c[10],
            ];
        }

        DB::transaction(function () use ($rows) {
            PostalCode::query()->delete();
            foreach (array_chunk($rows, 150) as $chunk) {
                PostalCode::insert($chunk);
            }
        });

        $this->info('Códigos postales cargados: '.count($rows));

        return self::SUCCESS;
    }

    private function download(): ?string
    {
        $this->line('Descargando ES.zip de GeoNames…');
        $zipPath = storage_path('app/ES.zip');
        $res = Http::timeout(120)->sink($zipPath)->get('https://download.geonames.org/export/zip/ES.zip');
        if (! $res->successful()) {
            return null;
        }
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return null;
        }
        $zip->extractTo(storage_path('app'), 'ES.txt');
        $zip->close();
        @unlink($zipPath);

        return storage_path('app/ES.txt');
    }
}
