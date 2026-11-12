<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_venue_crud_and_use_in_order(): void
    {
        config(['services.openrouteservice.key' => null]);
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post(route('venues.store'), [
            'name' => 'Finca La Concepción', 'address' => 'Camino del Colmenar', 'postal_code' => '29014',
            'city' => 'Málaga', 'distance_km' => 9.5, 'active' => 1,
        ])->assertRedirect(route('venues.index'));
        $venue = Venue::firstOrFail();
        $this->assertEquals(9.5, $venue->distance_km);

        $this->post(route('venues.store'), ['name' => 'X', 'postal_code' => '123'])->assertSessionHasErrors('postal_code');

        $this->get(route('venues.index'))->assertOk()->assertSee('Finca La Concepción');
        $this->get(route('orders.create', ['venue' => $venue->id]))->assertOk()
            ->assertSee('value="Camino del Colmenar"', false)->assertSee('value="9.5"', false);

        $client = Client::create(['type' => 'individual', 'name' => 'Ana']);
        $cat = Category::create(['name' => 'Sillas', 'code' => 'SIL']);
        $art = Article::create(['category_id' => $cat->id, 'reference' => 'SIL-0001', 'name' => 'Silla', 'rental_price' => 4, 'replacement_price' => 40, 'active' => true]);
        $art->addUnits(5);

        $this->post(route('orders.store'), [
            'client_id' => $client->id, 'venue_id' => $venue->id, 'start_date' => '2026-12-04', 'end_date' => '2026-12-06',
            'destination_address' => 'Camino del Colmenar', 'destination_postal_code' => '29014', 'destination_city' => 'Málaga',
            'distance_km' => 9.5, 'trucks' => 1, 'lines' => [['article_id' => $art->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame($venue->id, $order->venue_id);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Finca La Concepción');

        // Borrar la finca no rompe el pedido
        $this->delete(route('venues.destroy', $venue))->assertRedirect();
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Camino del Colmenar');
    }
}
