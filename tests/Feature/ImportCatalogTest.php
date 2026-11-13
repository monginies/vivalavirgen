<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_importa_articulos_con_foto_sin_duplicar(): void
    {
        Storage::fake('public');
        Category::create(['name' => 'Bancos', 'code' => 'BAN']);

        $dir = sys_get_temp_dir().'/vlv-import-'.uniqid();
        mkdir($dir);
        $img = imagecreatetruecolor(40, 30);
        imagejpeg($img, $dir.'/banco.jpg');
        file_put_contents($dir.'/a.csv', implode("\n", [
            'categoria;prefijo;nombre;descripcion;medidas;precio_alquiler;coste_reposicion;pagina_catalogo;foto;revisar',
            'Bancos;BAN;Banco Isa;Grande;140 x 30 x 50 cm;50.00;;9;banco.jpg;',
            'Cava;CAV;Cubo de cava;Color níquel;30 x 22 cm;6,50;;29;;Precio dudoso',
        ]));

        $this->artisan('catalog:import', ['csv' => $dir.'/a.csv', '--photos' => $dir])->assertSuccessful();
        $this->artisan('catalog:import', ['csv' => $dir.'/a.csv', '--photos' => $dir])->assertSuccessful();

        $this->assertSame(2, Article::count());
        $banco = Article::where('name', 'Banco Isa')->first();
        $this->assertSame('BAN-0001', $banco->reference);
        $this->assertSame('50.00', $banco->rental_price);
        $this->assertSame(1, $banco->photos()->count());
        Storage::disk('public')->assertExists($banco->photos->first()->thumb_path);
        $this->assertSame(0, $banco->units()->count());

        $cava = Article::where('name', 'Cubo de cava')->first();
        $this->assertSame('CAV-0001', $cava->reference);
        $this->assertSame('6.50', $cava->rental_price);
        $this->assertStringContainsString('Precio dudoso', $cava->notes);
    }
}
