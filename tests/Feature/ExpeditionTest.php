<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Support\Expedition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpeditionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        $this->user = User::factory()->create(['is_superadmin' => true]);
        $client = Client::create(['type' => 'company', 'name' => 'Bodas Bonitas SL']);
        $cat = Category::create(['name' => 'Salón', 'code' => 'SAL']);
        $make = fn ($name, $attrs = []) => Article::create($attrs + ['category_id' => $cat->id, 'reference' => Article::nextReference($cat),
            'name' => $name, 'rental_price' => 50, 'replacement_price' => 0, 'active' => true]);
        $sofa = $make('Sofá esparto');
        $chair = $make('Butaca esparto');
        $salon = $make('Salón Jardín', ['is_bundle' => true]);
        $salon->components()->sync([$sofa->id => ['quantity' => 1], $chair->id => ['quantity' => 2]]);

        $this->order = Order::create(['number' => 'PED-2026-0001', 'client_id' => $client->id, 'status' => OrderStatus::Requested,
            'start_date' => '2026-12-04', 'end_date' => '2026-12-06', 'expires_at' => now()->addDays(3)]);
        $this->order->lines()->create(['article_id' => $salon->id, 'quantity' => 2, 'unit_price' => 600]);
        $this->order->lines()->create(['article_id' => $chair->id, 'quantity' => 1, 'unit_price' => 78]);
    }

    public function test_rows_break_bundles_into_parts(): void
    {
        $rows = collect(Expedition::rows($this->order))->map(fn ($r) => [$r['article']->name, $r['quantity'], $r['part'], $r['from']])->all();

        $this->assertSame([
            ['Sofá esparto', 2, true, '2 × Salón Jardín'],
            ['Butaca esparto', 4, true, '2 × Salón Jardín'],
            ['Butaca esparto', 1, false, null],
        ], $rows);
    }

    public function test_rows_are_sorted_by_location(): void
    {
        $cat = Category::first();
        $make = fn ($name, $loc) => Article::create(['category_id' => $cat->id, 'reference' => Article::nextReference($cat),
            'name' => $name, 'location' => $loc, 'rental_price' => 1, 'replacement_price' => 0, 'active' => true]);
        foreach ([['Sin balda', null], ['Mesa', '10-A'], ['Vela', '2-B, 3-A'], ['Plato', '2-a'], ['Copa', '9-C']] as [$name, $loc]) {
            $this->order->lines()->create(['article_id' => $make($name, $loc)->id, 'quantity' => 1, 'unit_price' => 1]);
        }
        Article::where('name', 'Sofá esparto')->update(['location' => '1-A']);
        $this->order->update(['status' => OrderStatus::Paid, 'event_start_time' => '18:00', 'event_end_time' => '23:30', 'event_catering' => 'Catering Sol', 'event_florist' => 'No hay']);

        $names = collect(Expedition::rows($this->order->fresh()))->map(fn ($r) => $r['article']->name)->all();
        $this->assertSame(['Sofá esparto', 'Plato', 'Vela', 'Copa', 'Mesa', 'Butaca esparto', 'Butaca esparto', 'Sin balda'], $names);
        $this->actingAs($this->user)->get(route('expeditions.show', $this->order))->assertOk()->assertSee('18:00 – 23:30')->assertSee('Catering: Catering Sol')->assertDontSee('Florista:')
            ->assertSeeInOrder(['1-A', '2-a', '2-B, 3-A', '10-A', 'Sin localizador']);
    }

    public function test_location_is_saved_and_searchable(): void
    {
        $sofa = Article::where('name', 'Sofá esparto')->first();
        $this->actingAs($this->user)->put(route('articles.update', $sofa), [
            'category_id' => $sofa->category_id, 'name' => $sofa->name, 'location' => '4-C', 'rental_price' => 50, 'replacement_price' => 0, 'active' => 1,
        ])->assertRedirect();
        $this->assertSame('4-C', $sofa->fresh()->location);
        $this->actingAs($this->user)->get(route('articles.index', ['q' => '4-C']))->assertSee('Sofá esparto')->assertDontSee('Butaca esparto');
        $this->actingAs($this->user)->get(route('articles.show', $sofa))->assertSee('4-C');
    }

    public function test_logistics_data_is_saved_and_pdf_only_after_payment(): void
    {
        $this->actingAs($this->user)->patch(route('orders.logistics', $this->order), [
            'delivery_time' => '9:00–11:00', 'pickup_time' => '12:00', 'site_contact_name' => 'Juan', 'site_contact_phone' => '611222333',
            'logistics_notes' => 'Acceso por la puerta trasera',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Juan', $this->order->fresh()->site_contact_name);

        $this->actingAs($this->user)->get(route('orders.expedition', $this->order))->assertForbidden();
        $this->actingAs($this->user)->get(route('orders.show', $this->order))->assertOk()->assertSee('cuando el pedido esté pagado');

        $this->order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
        $this->actingAs($this->user)->get(route('orders.show', $this->order))->assertOk()->assertSee('Ir a la expedición')->assertDontSee('Dar salida');
        $res = $this->actingAs($this->user)->get(route('orders.expedition', $this->order))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
    }
}
