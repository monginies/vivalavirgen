<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientCategory;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\ClientApproved;
use App\Notifications\ClientResetPassword;
use App\Notifications\ClientVerifyEmail;
use App\Notifications\NewClientRegistered;
use App\Notifications\NewWebOrder;
use App\Notifications\OrderReceived;
use App\Support\Cart;
use App\Support\Push;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private Article $chair;

    protected function tearDown(): void
    {
        Push::$faked = null;
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        config(['services.openrouteservice.key' => null]);
        $cat = Category::create(['name' => 'Sillas', 'code' => 'SIL']);
        $this->chair = Article::create(['category_id' => $cat->id, 'reference' => 'SIL-0001', 'name' => 'Silla Crossback', 'rental_price' => 10, 'replacement_price' => 60, 'active' => true]);
        $this->chair->addUnits(20);
    }

    private function registerData(array $over = []): array
    {
        return $over + [
            'type' => 'company', 'wedding_planner' => 1, 'name' => 'Bodas Bonitas SL', 'tax_id' => 'B12345678',
            'contact_name' => 'Lucía', 'phone' => '600000000', 'email' => 'lucia@example.com',
            'address' => 'Calle Mar 1', 'postal_code' => '29730', 'city' => 'Rincón de la Victoria',
            'password' => 'secreta123', 'password_confirmation' => 'secreta123', 'privacy' => 1,
        ];
    }

    public function test_full_flow_register_verify_approve_and_order(): void
    {
        Notification::fake();

        // Catálogo público sin precios
        $this->get('/')->assertOk()->assertSee('Sillas')->assertSee(route('portal.collection', 'sillas'));
        $this->get(route('portal.collection', 'sillas'))->assertOk()->assertSee('Silla Crossback')->assertSee('Ingresa para ver precios')->assertDontSee('10,00 €');
        $this->get('/?q=crossback')->assertOk()->assertSee('Silla Crossback');
        $this->get(route('portal.article', 'SIL-0001'))->assertOk()->assertSee('Añadir al pedido');

        // Registro
        $this->post(route('portal.register'), $this->registerData())->assertRedirect(route('portal.account'));
        $client = Client::where('email', 'lucia@example.com')->firstOrFail();
        $this->assertSame('web', $client->source);
        $this->assertNull($client->client_category_id);
        $this->assertStringContainsString('wedding planner', $client->notes);
        $this->assertNotNull($client->privacy_accepted_at);
        $this->assertFalse($client->isApproved());
        Notification::assertSentTo($client, ClientVerifyEmail::class);

        // Confirmar email → aviso al equipo
        Push::fake();
        $url = URL::temporarySignedRoute('portal.verification.verify', now()->addHour(), ['id' => $client->id, 'hash' => sha1($client->email)]);
        $this->get($url)->assertRedirect();
        $this->assertTrue($client->fresh()->hasVerifiedEmail());
        Notification::assertSentTo(new AnonymousNotifiable, NewClientRegistered::class);
        $this->assertSame('Cliente nuevo pendiente de aprobar', Push::$faked[0]['title']);
        $this->assertStringContainsString('Bodas Bonitas SL', Push::$faked[0]['body']);

        // Sin aprobar: puede preparar el pedido pero no enviarlo
        $this->post(route('portal.cart.add', $this->chair), ['quantity' => 5])->assertRedirect();
        $this->get(route('portal.cart'))->assertOk()->assertSee('pendiente de aprobación');
        $this->post(route('portal.checkout'), ['event_date' => now()->next('Saturday')->toDateString(), 'city' => 'Málaga'])
            ->assertRedirect(route('portal.cart'))->assertSessionHas('error');
        $this->assertSame(0, Order::count());

        // El equipo lo aprueba
        $staff = User::factory()->create();
        $this->actingAs($staff)->get(route('clients.show', $client))->assertOk()->assertSee('Aprobar acceso');
        $this->actingAs($staff)->post(route('clients.approve', $client), ['client_category_id' => ClientCategory::where('name', 'Esparto')->value('id')])->assertRedirect();
        $this->assertTrue($client->fresh()->isApproved());
        Notification::assertSentTo($client, ClientApproved::class);

        // Ya ve su precio con descuento (10 % de 10 €)
        $this->actingAs($client->fresh(), 'client')->get(route('portal.collection', 'sillas'))->assertSee('9,00 €');
        $this->post(route('portal.cart.date'), ['event_date' => now()->addWeeks(3)->next('Saturday')->toDateString()])->assertRedirect();
        $this->get(route('portal.collection', 'sillas'))->assertSee('libres');

        // Pedido desde el carrito, a una finca
        $venue = Venue::create(['name' => 'Finca Test', 'city' => 'Málaga', 'postal_code' => '29014', 'distance_km' => 30, 'active' => true]);
        $saturday = now()->addWeeks(3)->next('Saturday')->toDateString();
        $this->actingAs($client->fresh(), 'client')->post(route('portal.checkout'), [
            'event_date' => $saturday, 'venue_id' => $venue->id, 'quantities' => [$this->chair->id => 6], 'notes' => 'Montaje a las 10',
        ])->assertRedirect();

        $order = Order::with('lines')->firstOrFail();
        $this->assertSame('web', $order->source);
        $this->assertSame(OrderStatus::Requested, $order->status);
        $this->assertSame(6, $order->lines->first()->quantity);
        $this->assertEquals(10, $order->discount_percent);
        $this->assertSame(30, $order->distance_km);
        $this->assertEquals(60, $order->transport_amount); // 2 × 30 × 0,60 = 36 → mínimo 60
        $this->assertSame(Carbon::parse($saturday)->previous('Friday')->toDateString(), $order->start_date->toDateString());
        $this->assertStringContainsString('Montaje a las 10', $order->notes);
        $this->assertSame(0, Cart::count());
        Notification::assertSentTo($client, OrderReceived::class);
        Notification::assertSentTo(new AnonymousNotifiable, NewWebOrder::class);

        $this->assertStringStartsWith('Pedido nuevo: ', collect(Push::$faked)->last()['title']);
        $this->actingAs($client->fresh(), 'client')->get(route('portal.orders.show', $order->number))->assertOk()->assertSee('Silla Crossback');
        $this->actingAs($staff)->get(route('orders.index'))->assertSee('Web');
    }

    public function test_cannot_order_more_than_available_or_see_others_orders(): void
    {
        $cat = ClientCategory::where('name', 'Esparto')->first();
        $client = Client::create(['type' => 'individual', 'name' => 'Ana', 'email' => 'ana@example.com', 'client_category_id' => $cat->id]);
        $client->forceFill(['password' => 'secreta123', 'email_verified_at' => now(), 'approved_at' => now()])->save();
        $other = Client::create(['type' => 'individual', 'name' => 'Otro']);
        $foreign = Order::create(['number' => 'PED-2026-0099', 'client_id' => $other->id, 'status' => OrderStatus::Requested, 'start_date' => '2026-12-04', 'end_date' => '2026-12-06']);

        $this->actingAs($client, 'client')->post(route('portal.cart.add', $this->chair), ['quantity' => 25]);
        $this->actingAs($client, 'client')->post(route('portal.checkout'), [
            'event_date' => now()->addWeeks(2)->next('Saturday')->toDateString(), 'postal_code' => '29730', 'city' => 'Rincón de la Victoria',
        ])->assertSessionHasErrors('items');
        $this->assertSame(1, Order::count());

        $this->actingAs($client, 'client')->get(route('portal.orders.show', $foreign->number))->assertNotFound();
    }

    public function test_login_and_password_reset(): void
    {
        Notification::fake();
        $client = Client::create(['type' => 'individual', 'name' => 'Ana', 'email' => 'ana@example.com']);
        $client->forceFill(['password' => 'secreta123'])->save();

        $this->post(route('portal.login'), ['email' => 'ana@example.com', 'password' => 'mal'])->assertSessionHasErrors('email');
        $this->post(route('portal.login'), ['email' => 'ana@example.com', 'password' => 'secreta123'])->assertRedirect(route('portal.home'));
        $this->assertAuthenticatedAs($client, 'client');
        $this->assertGuest('web'); // no da acceso al backoffice
        $this->get('/app')->assertRedirect(route('login'));
        $this->post(route('portal.logout'));

        $this->post(route('portal.password.email'), ['email' => 'ana@example.com'])->assertSessionHas('status');
        Notification::assertSentTo($client, ClientResetPassword::class, function ($n) use ($client) {
            $this->post(route('portal.password.update'), ['token' => $n->token, 'email' => $client->email, 'password' => 'nuevaclave1', 'password_confirmation' => 'nuevaclave1'])
                ->assertRedirect(route('portal.login'));

            return true;
        });
        $this->post(route('portal.login'), ['email' => 'ana@example.com', 'password' => 'nuevaclave1'])->assertRedirect(route('portal.home'));
    }

    public function test_register_validation_and_bot_trap(): void
    {
        $this->post(route('portal.register'), $this->registerData(['privacy' => null]))->assertSessionHasErrors('privacy');
        $this->post(route('portal.register'), $this->registerData(['website' => 'spam']))->assertSessionHasErrors('website');
        $this->assertSame(0, Client::count());
    }

    public function test_deleted_client_does_not_block_email(): void
    {
        $old = Client::create(['type' => 'individual', 'name' => 'Antiguo', 'email' => 'lucia@example.com']);
        $old->delete();

        $this->post(route('portal.register'), $this->registerData())->assertSessionHasNoErrors();
        $this->assertSame(1, Client::where('email', 'lucia@example.com')->count());

        $staff = User::factory()->create();
        $this->actingAs($staff)->post(route('clients.store'), ['type' => 'individual', 'name' => 'Duplicado', 'email' => 'lucia@example.com'])
            ->assertSessionHasErrors('email');
    }
}
