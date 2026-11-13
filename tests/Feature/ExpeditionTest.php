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
        $rows = collect(Expedition::rows($this->order))->map(fn ($r) => [$r['article']->name, $r['quantity'], $r['part']])->all();

        $this->assertSame([
            ['Salón Jardín', 2, false],
            ['Sofá esparto', 2, true],
            ['Butaca esparto', 4, true],
            ['Butaca esparto', 1, false],
        ], $rows);
    }

    public function test_logistics_data_is_saved_and_pdf_only_after_payment(): void
    {
        $this->actingAs($this->user)->patch(route('orders.logistics', $this->order), [
            'delivery_time' => '9:00–11:00', 'pickup_time' => '12:00', 'site_contact_name' => 'Juan', 'site_contact_phone' => '611222333',
            'logistics_notes' => 'Acceso por la puerta trasera',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Juan', $this->order->fresh()->site_contact_name);

        $this->actingAs($this->user)->get(route('orders.expedition', $this->order))->assertForbidden();
        $this->actingAs($this->user)->get(route('orders.show', $this->order))->assertOk()->assertSee('cuando el pedido está pagado');

        $this->order->update(['status' => OrderStatus::Paid, 'paid_at' => now()]);
        $this->actingAs($this->user)->get(route('orders.show', $this->order))->assertOk()->assertSee('Hoja de expedición (PDF)');
        $res = $this->actingAs($this->user)->get(route('orders.expedition', $this->order))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
    }
}
