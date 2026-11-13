<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $driver;

    private Order $order;

    private Article $chair;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        $this->admin = User::factory()->create(['is_superadmin' => true]);
        $this->driver = User::factory()->create(['role' => 'logistics', 'name' => 'Juan Logística']);
        $client = Client::create(['type' => 'company', 'name' => 'Bodas Bonitas SL']);
        $cat = Category::create(['name' => 'Sillas', 'code' => 'SIL']);
        $this->chair = Article::create(['category_id' => $cat->id, 'reference' => 'SIL-0001', 'name' => 'Silla Tiffany',
            'rental_price' => 4.5, 'replacement_price' => 60, 'active' => true]);
        $this->chair->addUnits(3);
        $this->order = Order::create(['number' => 'PED-2026-0001', 'client_id' => $client->id, 'status' => OrderStatus::Paid,
            'start_date' => '2026-12-04', 'end_date' => '2026-12-06', 'delivery_time' => '10:00', 'paid_at' => now()]);
        $this->order->lines()->create(['article_id' => $this->chair->id, 'quantity' => 2, 'unit_price' => 4.5]);
    }

    public function test_logistics_user_only_sees_expeditions(): void
    {
        $as = $this->actingAs($this->driver);

        $as->get(route('dashboard'))->assertRedirect(route('expeditions.index'));
        $as->get(route('expeditions.index'))->assertOk()->assertSee('PED-2026-0001')->assertSee('Dar salida')->assertDontSee('Clientes');
        $as->get(route('expeditions.show', $this->order))->assertOk()->assertSee('Silla Tiffany')->assertDontSee('4,50')->assertDontSee('ver pedido');
        $as->get(route('orders.expedition', $this->order))->assertOk();
        $as->get(route('expeditions.help'))->assertOk()->assertSee('Dar salida (al cargar en la nave)');
        $as->get(route('scan.show', [$this->order, 'salida']))->assertOk();
        $as->postJson(route('scan.scan', [$this->order, 'salida']), ['code' => $this->chair->units->first()->code])->assertJson(['level' => 'ok']);

        foreach ([route('orders.index'), route('orders.show', $this->order), route('clients.index'), route('articles.index'),
            route('calendar'), route('availability'), route('users.index'), route('settings.edit')] as $url) {
            $as->get($url)->assertForbidden();
        }
        $as->post(route('orders.transition', $this->order), ['status' => 'cancelled'])->assertForbidden();

        // Escanear una etiqueta con la cámara del móvil lleva a sus expediciones
        $as->get(route('qr.resolve', $this->chair->units->first()->qr_token))->assertRedirect(route('expeditions.index'));
    }

    public function test_admin_creates_logistics_user(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Pedro Almacén', 'email' => 'almacen@example.com', 'password' => 'secreta123', 'password_confirmation' => 'secreta123',
            'role' => 'logistics', 'is_superadmin' => 1, 'active' => 1,
        ])->assertSessionHasNoErrors();

        $u = User::where('email', 'almacen@example.com')->firstOrFail();
        $this->assertTrue($u->isLogistics());
        $this->assertFalse($u->is_superadmin); // logística nunca es superusuario
        $this->actingAs($this->admin)->get(route('users.index'))->assertSee('Logística');
        $this->actingAs($this->admin)->get(route('expeditions.index'))->assertOk();
    }
}
