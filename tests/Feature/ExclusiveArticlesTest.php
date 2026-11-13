<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientCategory;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExclusiveArticlesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $vip;

    private Client $other;

    private Category $cat;

    private Article $arch;

    private Article $chair;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        config(['services.openrouteservice.key' => null]);
        $this->user = User::factory()->create(['is_superadmin' => true]);
        $tier = ClientCategory::where('name', 'Esparto')->first();
        $this->vip = $this->client('Bodas Bonitas', 'vip@example.com', $tier?->id);
        $this->other = $this->client('Otra Planner', 'otra@example.com', $tier?->id);

        $this->cat = Category::create(['name' => 'Paneles', 'code' => 'PAN']);
        $this->arch = Article::create(['category_id' => $this->cat->id, 'reference' => 'PAN-0001', 'name' => 'Arco dorado',
            'rental_price' => 100, 'replacement_price' => 0, 'active' => true, 'exclusive' => true]);
        $this->arch->addUnits(2);
        $this->arch->clients()->sync([$this->vip->id]);
        $this->chair = Article::create(['category_id' => $this->cat->id, 'reference' => 'PAN-0002', 'name' => 'Banco libre',
            'rental_price' => 20, 'replacement_price' => 0, 'active' => true]);
        $this->chair->addUnits(5);
    }

    private function client(string $name, string $email, ?int $tier): Client
    {
        $c = Client::create(['type' => 'company', 'name' => $name, 'email' => $email, 'client_category_id' => $tier]);
        $c->forceFill(['password' => 'secreta123', 'email_verified_at' => now(), 'approved_at' => now()])->save();

        return $c;
    }

    public function test_only_authorized_clients_can_rent(): void
    {
        $this->assertSame([], Availability::blockedFor($this->vip->id, [$this->arch->id, $this->chair->id]));
        $this->assertSame([$this->arch->id], Availability::blockedFor($this->other->id, [$this->arch->id, $this->chair->id]));
        $this->assertSame([$this->arch->id], Availability::blockedFor(null, [$this->arch->id, $this->chair->id]));
    }

    public function test_bundle_with_exclusive_part_is_blocked_for_others(): void
    {
        $set = Article::create(['category_id' => $this->cat->id, 'reference' => 'PAN-0003', 'name' => 'Rincón arco',
            'rental_price' => 150, 'replacement_price' => 0, 'active' => true, 'is_bundle' => true]);
        $set->components()->sync([$this->arch->id => ['quantity' => 1], $this->chair->id => ['quantity' => 2]]);

        $this->assertSame([$set->id], Availability::blockedFor($this->other->id, [$set->id]));
        $this->assertSame([], Availability::blockedFor($this->vip->id, [$set->id]));
    }

    public function test_backoffice_order_rejects_unauthorized_client(): void
    {
        $post = fn (Client $c) => $this->actingAs($this->user)->post(route('orders.store'), [
            'client_id' => $c->id, 'start_date' => '2026-12-04', 'end_date' => '2026-12-06', 'trucks' => 1,
            'lines' => [['article_id' => $this->arch->id, 'quantity' => 1]],
        ]);

        $post($this->other)->assertSessionHasErrors('lines');
        $this->assertSame(0, Order::count());
        $post($this->vip)->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());
    }

    public function test_portal_shows_it_without_availability_to_others(): void
    {
        // Visitante y otro cliente: lo ven, sin disponibilidad ni botón
        $this->get(route('portal.article', 'PAN-0001'))->assertOk()->assertSee('Arco dorado')->assertSee('No disponible')->assertDontSee('Añadir al pedido');
        $this->actingAs($this->other, 'client')->get(route('portal.collection', 'paneles'))->assertOk()->assertSee('Arco dorado')->assertSee('No disponible');
        $this->actingAs($this->other, 'client')->post(route('portal.cart.add', $this->arch), ['quantity' => 1])->assertSessionHas('error');

        // Cliente autorizado: puede pedirlo
        $this->actingAs($this->vip, 'client')->get(route('portal.article', 'PAN-0001'))->assertOk()->assertSee('Añadir al pedido')->assertDontSee('No disponible');
        $this->actingAs($this->vip, 'client')->post(route('portal.cart.add', $this->arch), ['quantity' => 1])->assertSessionMissing('error');
        $this->actingAs($this->vip, 'client')->post(route('portal.checkout'), [
            'event_date' => now()->addWeeks(2)->next('Saturday')->toDateString(), 'postal_code' => '29730', 'city' => 'Rincón de la Victoria',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Order::where('client_id', $this->vip->id)->count());
    }

    public function test_backoffice_saves_exclusive_clients(): void
    {
        $this->actingAs($this->user)->put(route('articles.update', $this->chair), [
            'category_id' => $this->cat->id, 'name' => 'Banco libre', 'rental_price' => 20, 'replacement_price' => 0, 'active' => 1,
            'exclusive' => 1, 'clients' => [$this->other->id],
        ])->assertSessionHasNoErrors();

        $this->chair->refresh();
        $this->assertTrue($this->chair->exclusive);
        $this->assertSame([$this->other->id], $this->chair->clients->pluck('id')->all());
        $this->actingAs($this->user)->get(route('articles.show', $this->chair))->assertOk()->assertSee('Exclusivo para')->assertSee('Otra Planner');
        $this->actingAs($this->user)->get(route('articles.edit', $this->chair))->assertOk()->assertSee('Clientes autorizados');

        // Al quitar la exclusividad se borran los autorizados
        $this->actingAs($this->user)->put(route('articles.update', $this->chair), [
            'category_id' => $this->cat->id, 'name' => 'Banco libre', 'rental_price' => 20, 'replacement_price' => 0, 'active' => 1,
            'exclusive' => 0, 'clients' => [$this->other->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->chair->clients()->count());
    }
}
