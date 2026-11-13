<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundlesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Category $cat;

    private Article $sofa;

    private Article $chair;

    private Article $salon;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        $this->user = User::factory()->create(['is_superadmin' => true]);
        $this->client = Client::create(['type' => 'individual', 'name' => 'Ana Pérez']);
        $this->cat = Category::create(['name' => 'Salón', 'code' => 'SAL']);
        $this->sofa = $this->article('Sofá esparto', 175);
        $this->sofa->addUnits(3);
        $this->chair = $this->article('Butaca esparto', 78);
        $this->chair->addUnits(5);
        // Salón = 1 sofá + 2 butacas, a 300 € (las piezas sueltas suman 331 €)
        $this->salon = $this->article('Salón Jardín', 300, ['is_bundle' => true]);
        $this->salon->components()->sync([$this->sofa->id => ['quantity' => 1], $this->chair->id => ['quantity' => 2]]);
    }

    private function article(string $name, float $price, array $attrs = []): Article
    {
        return Article::create($attrs + [
            'category_id' => $this->cat->id, 'reference' => Article::nextReference($this->cat), 'name' => $name,
            'rental_price' => $price, 'replacement_price' => 0, 'active' => true,
        ]);
    }

    private function book(Article $a, int $qty, string $start = '2026-12-04', string $end = '2026-12-06'): Order
    {
        $o = Order::create([
            'number' => Order::nextNumber(), 'client_id' => $this->client->id,
            'status' => OrderStatus::Requested, 'start_date' => $start, 'end_date' => $end,
            'expires_at' => now()->addDays(3),
        ]);
        $o->lines()->create(['article_id' => $a->id, 'quantity' => $qty, 'unit_price' => $a->rental_price]);

        return $o;
    }

    private function free(Article $a, string $from = '2026-12-05', string $to = '2026-12-05'): int
    {
        return Availability::forArticles([$a->id], $from, $to)[$a->id]['available'];
    }

    public function test_bundle_availability_is_limited_by_its_scarcest_part(): void
    {
        // 3 sofás y 5 butacas (2 por salón) → se pueden montar 2
        $this->assertSame(2, $this->free($this->salon));
        $this->assertSame(2, Availability::capacity([$this->salon->id])[$this->salon->id]);
    }

    public function test_renting_a_bundle_takes_its_parts_out_of_individual_stock(): void
    {
        $this->book($this->salon, 1);

        $this->assertSame(2, $this->free($this->sofa));
        $this->assertSame(3, $this->free($this->chair));
        $this->assertSame(1, $this->free($this->salon));
        // Otras fechas, todo libre
        $this->assertSame(5, $this->free($this->chair, '2026-12-10', '2026-12-11'));
    }

    public function test_renting_parts_reduces_bundle_availability(): void
    {
        $this->book($this->chair, 4);

        $this->assertSame(0, $this->free($this->salon));
        $this->assertSame(3, $this->free($this->sofa));
    }

    public function test_order_checks_parts_shared_between_bundle_and_loose_lines(): void
    {
        // 1 salón (2 butacas) + 4 butacas sueltas = 6 > 5
        $this->actingAs($this->user)->post(route('orders.store'), [
            'client_id' => $this->client->id, 'start_date' => '2026-12-04', 'end_date' => '2026-12-06', 'trucks' => 1,
            'lines' => [
                ['article_id' => $this->salon->id, 'quantity' => 1],
                ['article_id' => $this->chair->id, 'quantity' => 4],
            ],
        ])->assertSessionHasErrors('lines');
        $this->assertSame(0, Order::count());

        $this->actingAs($this->user)->post(route('orders.store'), [
            'client_id' => $this->client->id, 'start_date' => '2026-12-04', 'end_date' => '2026-12-06', 'trucks' => 1,
            'lines' => [
                ['article_id' => $this->salon->id, 'quantity' => 1],
                ['article_id' => $this->chair->id, 'quantity' => 3],
            ],
        ])->assertSessionHasNoErrors();

        $order = Order::with('lines')->firstOrFail();
        // Precio propio del combinado, no la suma de piezas
        $this->assertEquals(300 + 3 * 78, $order->subtotal());
        $this->actingAs($this->user)->get(route('orders.show', $order))->assertOk()->assertSee('Incluye: 1 × Sofá esparto, 2 × Butaca esparto');
    }

    public function test_bundle_without_parts_cannot_be_booked(): void
    {
        $empty = $this->article('Salón Terraza', 680, ['is_bundle' => true]);
        $this->assertSame(0, $this->free($empty));
        $this->assertNotEmpty(Availability::shortages([['article_id' => $empty->id, 'quantity' => 1]], '2026-12-04', '2026-12-06'));
    }

    public function test_backoffice_creates_and_edits_bundle(): void
    {
        $this->actingAs($this->user)->post(route('articles.store'), [
            'category_id' => $this->cat->id, 'name' => 'Rincón', 'rental_price' => 200, 'replacement_price' => 0,
            'active' => 1, 'is_bundle' => 1, 'initial_units' => 5,
            'components' => [
                ['article_id' => $this->sofa->id, 'quantity' => 1],
                ['article_id' => $this->chair->id, 'quantity' => 1],
                ['article_id' => $this->chair->id, 'quantity' => 1],
                ['article_id' => '', 'quantity' => 1],
            ],
        ])->assertSessionHasNoErrors();

        $rincon = Article::where('name', 'Rincón')->firstOrFail();
        $this->assertTrue($rincon->is_bundle);
        $this->assertSame(0, $rincon->units()->count()); // un combinado no crea unidades
        $this->assertEquals([$this->sofa->id => 1, $this->chair->id => 2], $rincon->components->pluck('pivot.quantity', 'id')->all());

        $this->actingAs($this->user)->get(route('articles.show', $rincon))->assertOk()->assertSee('Piezas')->assertDontSee('Imprimir etiquetas QR');
        $this->actingAs($this->user)->get(route('articles.edit', $rincon))->assertOk();
        $this->actingAs($this->user)->get(route('articles.index'))->assertOk()->assertSee('montables')->assertSee('3 / 3');

        // Sin piezas no se puede guardar como combinado
        $this->actingAs($this->user)->put(route('articles.update', $rincon), [
            'category_id' => $this->cat->id, 'name' => 'Rincón', 'rental_price' => 200, 'replacement_price' => 0,
            'is_bundle' => 1, 'components' => [['article_id' => '', 'quantity' => 1]],
        ])->assertSessionHasErrors('components');

        // No se pueden meter combinados dentro de combinados
        $this->actingAs($this->user)->put(route('articles.update', $rincon), [
            'category_id' => $this->cat->id, 'name' => 'Rincón', 'rental_price' => 200, 'replacement_price' => 0,
            'is_bundle' => 1, 'components' => [['article_id' => $this->salon->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('components');
    }

    public function test_article_with_units_cannot_become_bundle_and_parts_cannot_be_deleted(): void
    {
        $this->actingAs($this->user)->put(route('articles.update', $this->sofa), [
            'category_id' => $this->cat->id, 'name' => 'Sofá esparto', 'rental_price' => 175, 'replacement_price' => 0,
            'is_bundle' => 1, 'components' => [['article_id' => $this->chair->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('is_bundle');

        $this->actingAs($this->user)->delete(route('articles.destroy', $this->chair))->assertSessionHas('error');
        $this->assertNotSoftDeleted($this->chair);

        $this->actingAs($this->user)->post(route('articles.units.store', $this->salon), ['count' => 1])->assertStatus(422);
    }

    public function test_portal_shows_bundle_contents(): void
    {
        $this->get(route('portal.article', $this->salon->reference))->assertOk()
            ->assertSee('Incluye')->assertSee('Butaca esparto');
    }
}
