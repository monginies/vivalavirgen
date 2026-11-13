<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Support\Push;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribe_unsubscribe_and_dashboard_card(): void
    {
        Setting::flushCache();
        config(['services.webpush.public' => 'BPUBLICKEY', 'services.webpush.private' => 'PRIVATE']);
        $u = User::factory()->create();

        $this->actingAs($u)->get(route('dashboard'))->assertOk()->assertSee('Avisos en este dispositivo');
        $data = ['endpoint' => 'https://push.example.com/abc', 'keys' => ['p256dh' => 'P', 'auth' => 'A']];
        $this->actingAs($u)->postJson(route('push.subscribe'), $data)->assertOk();
        $this->actingAs($u)->postJson(route('push.subscribe'), $data)->assertOk();
        $this->assertSame(1, PushSubscription::count());

        $this->actingAs($u)->postJson(route('push.unsubscribe'), ['endpoint' => $data['endpoint']])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_expiry_alert_says_what_is_missing(): void
    {
        Push::fake();
        $client = Client::create(['type' => 'individual', 'name' => 'Ana']);
        $make = fn ($n, $status, $extra = []) => Order::create($extra + ['number' => $n, 'client_id' => $client->id, 'status' => $status,
            'start_date' => '2026-12-04', 'end_date' => '2026-12-06', 'expires_at' => now()->addHours(5)]);
        $make('PED-2026-0001', OrderStatus::Requested);
        $make('PED-2026-0002', OrderStatus::Accepted);
        $make('PED-2026-0003', OrderStatus::ProformaSent, ['proforma_sent_at' => now()->setDate(2026, 11, 14)]);

        $this->artisan('orders:expiry-alerts')->assertSuccessful();
        $bodies = collect(Push::$faked)->pluck('body', 'title');
        $this->assertStringContainsString('sin aprobar', $bodies['Caduca pronto: PED-2026-0001']);
        $this->assertStringContainsString('proforma sin enviar', $bodies['Caduca pronto: PED-2026-0002']);
        $this->assertStringContainsString('proforma enviada el 14/11, sin pagar', $bodies['Caduca pronto: PED-2026-0003']);
        Push::$faked = null;
    }

    public function test_expiry_alert_runs_once_per_order_and_resets_on_extend(): void
    {
        Setting::flushCache();
        config(['services.webpush.public' => null]); // sin claves no se envía nada, pero se marca
        $client = Client::create(['type' => 'individual', 'name' => 'Ana']);
        $o = Order::create(['number' => 'PED-2026-0001', 'client_id' => $client->id, 'status' => OrderStatus::ProformaSent,
            'start_date' => '2026-12-04', 'end_date' => '2026-12-06', 'expires_at' => now()->addHours(10)]);
        $later = Order::create(['number' => 'PED-2026-0002', 'client_id' => $client->id, 'status' => OrderStatus::Requested,
            'start_date' => '2026-12-04', 'end_date' => '2026-12-06', 'expires_at' => now()->addDays(2)]);

        $this->artisan('orders:expiry-alerts')->expectsOutput('Avisos enviados: 1')->assertSuccessful();
        $this->assertNotNull($o->fresh()->expiry_alerted_at);
        $this->assertNull($later->fresh()->expiry_alerted_at);
        $this->artisan('orders:expiry-alerts')->expectsOutput('Avisos enviados: 0');

        $this->actingAs(User::factory()->create())->post(route('orders.extend', $o), ['days' => 3]);
        $this->assertNull($o->fresh()->expiry_alerted_at);
    }
}
