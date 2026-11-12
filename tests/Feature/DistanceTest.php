<?php

namespace Tests\Feature;

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
            ->assertJson(['km' => 58.3, 'label' => 'Marbella, AN, España']);

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
            ->push(['features' => [['geometry' => ['coordinates' => [-4.45, 36.69]], 'properties' => ['label' => 'Base']]]])
            ->push(['features' => []])]);

        $this->actingAs(User::factory()->make(['id' => 1]))
            ->getJson(route('orders.distance', ['address' => 'zzz']))
            ->assertStatus(422)->assertJsonPath('error', 'No se encuentra esa dirección. Prueba a añadir la población o el código postal.');
    }
}
