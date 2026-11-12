<?php

namespace Tests\Feature;

use App\Models\PostalCode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DistanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
    }

    public function test_calculates_driving_km_from_base(): void
    {
        config(['services.openrouteservice.key' => 'test-key', 'cache.default' => 'array']);
        Http::fake([
            'api.openrouteservice.org/geocode/*' => Http::sequence()
                ->push(['features' => [['geometry' => ['coordinates' => [-4.45, 36.69]], 'properties' => ['label' => 'Calle Driza 13, Málaga']]]])
                ->push(['features' => [['geometry' => ['coordinates' => [-4.88, 36.51]], 'properties' => ['label' => 'Marbella, AN, España']]]]),
            'api.openrouteservice.org/v2/directions/*' => Http::response(['routes' => [['summary' => ['distance' => 58340.2]]]]),
        ]);

        $this->actingAs(User::factory()->make(['id' => 1]))
            ->getJson(route('orders.distance', ['address' => 'Finca X', 'city' => 'Marbella']))
            ->assertOk()
            ->assertJson(['km' => '60', 'exact' => '58.3', 'label' => 'Marbella, AN, España · redondeado a 60 km']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'directions') && $r->header('Authorization') === ['test-key']);
    }

    public function test_reports_missing_address_and_missing_key(): void
    {
        $user = User::factory()->make(['id' => 1]);

        config(['services.openrouteservice.key' => null]);
        $this->actingAs($user)->getJson(route('orders.distance', ['city' => 'Marbella']))
            ->assertStatus(422)->assertJsonPath('error', 'Falta la clave de OpenRouteService en el servidor.');

        config(['services.openrouteservice.key' => 'k', 'cache.default' => 'array']);
        $this->actingAs($user)->getJson(route('orders.distance'))
            ->assertStatus(422)->assertJsonPath('error', 'Escribe primero la dirección o la población del destino.');
    }

    public function test_address_not_found(): void
    {
        config(['services.openrouteservice.key' => 'k', 'cache.default' => 'array']);
        Http::fake(['api.openrouteservice.org/geocode/*' => Http::sequence()
            ->push(['features' => [['geometry' => ['coordinates' => [-4.45, 36.69]], 'properties' => ['label' => 'Calle Driza 13, Málaga']]]])
            ->push(['features' => []])]);

        $this->actingAs(User::factory()->make(['id' => 1]))
            ->getJson(route('orders.distance', ['address' => 'zzz']))
            ->assertStatus(422)->assertJsonPath('error', 'No se encuentra esa dirección a menos de 300 km de la base. Prueba a añadir la población o el código postal.');
    }

    public function test_postal_code_locates_destination_anywhere_in_spain(): void
    {
        config(['services.openrouteservice.key' => 'k', 'cache.default' => 'array']);
        PostalCode::insert([
            ['code' => '41001', 'place' => 'Sevilla', 'municipality' => 'Sevilla', 'province' => 'Sevilla', 'lat' => 37.3886, 'lon' => -5.9953],
            ['code' => '18009', 'place' => 'Granada', 'municipality' => 'Granada', 'province' => 'Granada', 'lat' => 37.18, 'lon' => -3.6],
        ]);
        Http::fake([
            'api.openrouteservice.org/geocode/*' => Http::sequence()
                ->push(['features' => [['geometry' => ['coordinates' => [-4.45, 36.69]], 'properties' => ['label' => 'Calle Driza 13, Málaga', 'layer' => 'address']]]])
                ->push(['features' => [['geometry' => ['coordinates' => [-5.99, 37.38]], 'properties' => ['label' => 'Calle Sierpes, Sevilla', 'layer' => 'street']]]]),
            'api.openrouteservice.org/v2/directions/*' => Http::response(['routes' => [['summary' => ['distance' => 207100]]]]),
        ]);
        $user = User::factory()->make(['id' => 1]);

        // Dirección que no aparece: se usa el centro del CP
        $this->actingAs($user)->getJson(route('orders.distance', ['address' => 'Hacienda Inventada', 'postal_code' => '41001']))
            ->assertOk()->assertJson(['km' => '210'])
            ->assertJsonPath('label', 'CP 41001 · Sevilla (no se ha encontrado la dirección exacta; se toma el centro del código postal) · redondeado a 210 km');

        $this->actingAs($user)->getJson(route('orders.distance', ['postal_code' => '99999']))
            ->assertStatus(422)->assertJsonPath('error', 'No conozco el código postal 99999. Revísalo o déjalo en blanco.');

        $this->actingAs($user)->getJson(route('postal-codes.show', '18009'))
            ->assertOk()->assertJson(['found' => true, 'municipality' => 'Granada', 'province' => 'Granada']);
    }
}
