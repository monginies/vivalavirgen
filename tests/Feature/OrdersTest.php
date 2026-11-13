<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UnitStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Article $chair;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        $this->user = User::factory()->create(['is_superadmin' => true]);
        $this->client = Client::create(['type' => 'individual', 'name' => 'Ana Pérez']);
        $cat = Category::create(['name' => 'Sillas', 'code' => 'SIL']);
        $this->chair = Article::create([
            'category_id' => $cat->id, 'reference' => 'SIL-0001', 'name' => 'Silla Tiffany',
            'rental_price' => 4.5, 'replacement_price' => 60, 'active' => true,
        ]);
        $this->chair->addUnits(10);
    }

    private function order(string $start, string $end, int $qty, array $attrs = []): Order
    {
        $o = Order::create($attrs + [
            'number' => Order::nextNumber(), 'client_id' => $this->client->id,
            'status' => OrderStatus::Requested, 'start_date' => $start, 'end_date' => $end,
            'expires_at' => now()->addDays(3),
        ]);
        $o->lines()->create(['article_id' => $this->chair->id, 'quantity' => $qty, 'unit_price' => 4.5]);

        return $o;
    }

    private function free(string $from, string $to): int
    {
        return Availability::forArticles([$this->chair->id], $from, $to)[$this->chair->id]['available'];
    }

    public function test_capacity_ignores_retired_and_maintenance_units(): void
    {
        $this->chair->units()->take(2)->get()->each->update(['status' => UnitStatus::Retired]);
        $this->chair->units()->where('status', 'available')->first()->update(['status' => UnitStatus::Maintenance]);

        $this->assertSame(7, $this->free('2026-12-04', '2026-12-06'));
    }

    public function test_overlapping_orders_reduce_availability_but_separate_dates_do_not(): void
    {
        $this->order('2026-12-04', '2026-12-06', 6);

        $this->assertSame(4, $this->free('2026-12-05', '2026-12-05'));
        $this->assertSame(10, $this->free('2026-12-07', '2026-12-08'));
    }

    public function test_reserved_is_peak_per_day_not_sum_over_range(): void
    {
        $this->order('2026-12-01', '2026-12-02', 6);
        $this->order('2026-12-05', '2026-12-06', 7);

        // En todo el intervalo nunca coinciden: el pico es 7
        $this->assertSame(3, $this->free('2026-12-01', '2026-12-06'));
    }

    public function test_overdue_and_cancelled_orders_do_not_block(): void
    {
        $this->order('2026-12-04', '2026-12-06', 6, ['expires_at' => now()->subHour()]);
        $this->order('2026-12-04', '2026-12-06', 3, ['status' => OrderStatus::Cancelled]);

        $this->assertSame(10, $this->free('2026-12-04', '2026-12-06'));
        $this->assertSame(1, Order::expireOverdue());
    }

    public function test_paid_orders_do_not_expire(): void
    {
        $this->order('2026-12-04', '2026-12-06', 6, ['status' => OrderStatus::Paid, 'expires_at' => null]);

        $this->assertSame(0, Order::expireOverdue());
        $this->assertSame(4, $this->free('2026-12-04', '2026-12-06'));
    }

    public function test_delivered_material_blocks_until_closed_even_after_return_date(): void
    {
        $o = $this->order('2026-11-01', '2026-11-03', 5, ['status' => OrderStatus::Delivered, 'expires_at' => null]);

        $this->assertSame(5, $this->free('2026-12-20', '2026-12-21'));
        $o->update(['status' => OrderStatus::Closed]);
        $this->assertSame(10, $this->free('2026-12-20', '2026-12-21'));
    }

    public function test_create_order_prereserves_and_calculates_transport(): void
    {
        $res = $this->actingAs($this->user)->post(route('orders.store'), [
            'client_id' => $this->client->id,
            'start_date' => '2026-12-04', 'end_date' => '2026-12-06',
            'distance_km' => 100, 'trucks' => 2,
            'deposit_amount' => 150,
            'lines' => [['article_id' => $this->chair->id, 'quantity' => 8, 'unit_price' => 4.5]],
        ]);

        $order = Order::firstOrFail();
        $res->assertRedirect(route('orders.show', $order));
        $this->assertSame(OrderStatus::Requested, $order->status);
        $this->assertSame('PED-'.now()->year.'-0001', $order->number);
        // 2 trayectos × 100 km × 0,60 €/km × 2 camiones = 240
        $this->assertEquals(240, $order->transport_amount);
        $this->assertTrue($order->expires_at->between(now()->addDays(3)->subMinute(), now()->addDays(3)->addMinute()));
        // 36 € material + 240 transporte = 276 base, IVA 21 % (57,96), retención 7 % (19,32)
        $this->assertEquals(276, $order->taxableBase());
        $this->assertEquals(7, $order->withholding_rate);
        $this->assertEquals(19.32, $order->withholdingAmount());
        $this->assertEquals(314.64, $order->total());
        // La fianza (150) no lleva retención
        $this->assertEquals(464.64, $order->amountDue());

        $this->actingAs($this->user)->get(route('orders.show', $order))->assertOk()->assertSee('PED-');
    }

    public function test_withholding_is_on_base_with_discount_and_transport_not_on_deposit(): void
    {
        $o = $this->order('2026-12-04', '2026-12-06', 10, [
            'discount_percent' => 10, 'transport_amount' => 60, 'deposit_amount' => 150, 'vat_rate' => 21, 'withholding_rate' => 7,
        ]);
        $o->load('lines');
        // Material 45 − 10 % = 40,50 + 60 transporte = 100,50 base
        $this->assertEquals(100.5, $o->taxableBase());
        $this->assertEquals(21.11, $o->vatAmount());
        $this->assertEquals(7.04, $o->withholdingAmount());
        $this->assertEquals(114.57, $o->total());
        $this->assertEquals(264.57, $o->amountDue());

        $this->actingAs($this->user)->get(route('orders.show', $o))->assertOk()->assertSee('Retención IRPF 7 %');
        $this->actingAs($this->user)->get(route('orders.proforma', $o))->assertOk();
    }

    public function test_dashboard_highlights_orders_to_approve(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertDontSee('pendiente de aprobar');

        $o = $this->order('2026-12-04', '2026-12-06', 2, ['source' => 'web']);
        $this->actingAs($this->user)->get(route('dashboard'))->assertOk()
            ->assertSee('1 pedido pendiente de aprobar')->assertSee($o->number)->assertSee('Revisar');

        $o->update(['status' => OrderStatus::Accepted]);
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee('pendiente de aprobar')
            ->assertSee('1 pedido pendiente de pago')->assertSee('+3 días');

        $o->update(['status' => OrderStatus::ProformaSent, 'proforma_sent_at' => now()]);
        $this->actingAs($this->user)->get(route('dashboard'))->assertSee('1 pedido pendiente de pago')->assertSee('enviada');

        $o->update(['status' => OrderStatus::Paid, 'expires_at' => null]);
        $this->actingAs($this->user)->get(route('dashboard'))->assertDontSee('pendiente de pago');
    }

    public function test_transport_minimum_applies(): void
    {
        $this->assertEquals(60, Order::calculateTransport(10, 1));
        $this->assertEquals(0, Order::calculateTransport(null, 1));
    }

    public function test_cannot_book_more_than_available(): void
    {
        $this->order('2026-12-04', '2026-12-06', 6);

        $this->actingAs($this->user)->post(route('orders.store'), [
            'client_id' => $this->client->id,
            'start_date' => '2026-12-05', 'end_date' => '2026-12-07', 'trucks' => 1,
            'lines' => [['article_id' => $this->chair->id, 'quantity' => 5]],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(1, Order::count());
    }

    public function test_editing_an_order_does_not_count_its_own_reservation(): void
    {
        $o = $this->order('2026-12-04', '2026-12-06', 8);

        $this->actingAs($this->user)->put(route('orders.update', $o), [
            'client_id' => $this->client->id,
            'start_date' => '2026-12-04', 'end_date' => '2026-12-06', 'trucks' => 1,
            'lines' => [['article_id' => $this->chair->id, 'quantity' => 10, 'unit_price' => 4]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(10, $o->lines()->first()->quantity);
    }

    public function test_status_flow_and_reactivation(): void
    {
        $o = $this->order('2026-12-04', '2026-12-06', 4);
        $go = fn ($s) => $this->actingAs($this->user)->from(route('orders.show', $o))->post(route('orders.transition', $o), ['status' => $s]);

        $go('paid')->assertSessionHas('error'); // no se puede saltar pasos
        $go('accepted');
        $go('proforma_sent');
        $this->assertNull($o->fresh()->paid_at);
        $this->travelTo(now()->setTime(17, 45));
        $go('paid');
        $this->assertNull($o->fresh()->expires_at);
        $this->assertSame(OrderStatus::Paid, $o->fresh()->status);
        // Queda registrado cuándo y quién lo marcó como pagado
        $this->assertSame(now()->format('Y-m-d H:i'), $o->fresh()->paid_at->format('Y-m-d H:i'));
        $this->assertSame($this->user->id, $o->fresh()->paid_by);
        $this->actingAs($this->user)->get(route('orders.show', $o))->assertSee('Marcado como pagado el')->assertSee('17:45');
        $this->travelBack();

        $expired = $this->order('2026-12-10', '2026-12-11', 2, ['status' => OrderStatus::Expired]);
        $this->actingAs($this->user)->post(route('orders.transition', $expired), ['status' => 'requested']);
        $this->assertSame(OrderStatus::Requested, $expired->fresh()->status);
        $this->assertTrue($expired->fresh()->expires_at->isFuture());
    }

    public function test_pages_render(): void
    {
        $this->order('2026-12-04', '2026-12-06', 4);
        $this->actingAs($this->user);

        $this->get(route('orders.index'))->assertOk()->assertSee('Ana Pérez');
        $this->get(route('orders.create'))->assertOk();
        $this->get(route('calendar', ['month' => '2026-12']))->assertOk()->assertSee('Ana Pérez');
        $this->get(route('availability', ['from' => '2026-12-04', 'to' => '2026-12-06']))->assertOk()->assertSee('Silla Tiffany');
        $this->get(route('settings.edit'))->assertOk();
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('clients.show', $this->client))->assertOk()->assertSee('PED-');
    }

    public function test_settings_are_superadmin_only_and_saved(): void
    {
        $staff = User::factory()->create(['is_superadmin' => false]);
        $this->actingAs($staff)->get(route('settings.edit'))->assertForbidden();

        $this->actingAs($this->user)->put(route('settings.update'), [
            'transport_price_per_km' => '0.8', 'transport_minimum' => '50', 'deposit_amount' => '200',
            'prereservation_days' => '3', 'vat_rate' => '21', 'withholding_rate' => '7', 'base_address' => 'C/ Driza, 13',
        ])->assertSessionHasNoErrors();

        Setting::flushCache();
        $this->assertEquals(0.8, Setting::number('transport_price_per_km'));
    }

    public function test_weekend_event_blocks_friday_to_sunday(): void
    {
        // Sábado 5/12/2026 → viernes 4 a domingo 6
        $this->assertSame(['2026-12-04', '2026-12-06'], Order::weekendBlock('2026-12-05', null, null));
        // Domingo y viernes también
        $this->assertSame(['2026-12-04', '2026-12-06'], Order::weekendBlock('2026-12-06', '2026-12-06', '2026-12-06'));
        $this->assertSame(['2026-12-04', '2026-12-06'], Order::weekendBlock('2026-12-04', null, null));
        // Fechas más amplias se respetan
        $this->assertSame(['2026-12-03', '2026-12-07'], Order::weekendBlock('2026-12-05', '2026-12-03', '2026-12-07'));
        // Entre semana no cambia nada
        $this->assertSame(['2026-12-09', '2026-12-09'], Order::weekendBlock('2026-12-09', null, null));

        $this->actingAs($this->user)->post(route('orders.store'), [
            'client_id' => $this->client->id, 'event_date' => '2026-12-05',
            'start_date' => '2026-12-05', 'end_date' => '2026-12-05', 'trucks' => 1,
            'lines' => [['article_id' => $this->chair->id, 'quantity' => 10]],
        ])->assertSessionHasNoErrors();
        $o = Order::firstOrFail();
        $this->assertSame(['2026-12-04', '2026-12-06'], [$o->start_date->toDateString(), $o->end_date->toDateString()]);
        // El domingo las sillas siguen ocupadas
        $this->assertSame(0, $this->free('2026-12-06', '2026-12-06'));

        $this->actingAs($this->user)->post(route('orders.store'), [
            'client_id' => $this->client->id, 'event_date' => '2026-12-16',
            'start_date' => '2026-12-10', 'end_date' => '2026-12-11', 'trucks' => 1,
            'lines' => [['article_id' => $this->chair->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('event_date');
    }
}
