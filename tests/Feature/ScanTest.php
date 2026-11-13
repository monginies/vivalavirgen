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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Client $client;

    private Article $chair;

    private Article $table;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        $this->user = User::factory()->create(['is_superadmin' => true]);
        $this->client = Client::create(['type' => 'company', 'name' => 'Bodas Bonitas SL']);
        $cat = Category::create(['name' => 'Salón', 'code' => 'SAL']);
        $mk = fn ($n, $a = []) => Article::create($a + ['category_id' => $cat->id, 'reference' => Article::nextReference($cat), 'name' => $n,
            'rental_price' => 10, 'replacement_price' => 80, 'active' => true]);
        $this->chair = $mk('Butaca');
        $this->chair->addUnits(4);
        $this->table = $mk('Mesa');
        $this->table->addUnits(2);
        $set = $mk('Rincón', ['is_bundle' => true]);
        $set->components()->sync([$this->chair->id => ['quantity' => 2], $this->table->id => ['quantity' => 1]]);
        $this->order = $this->paidOrder([[$set->id, 1]]);
    }

    private function paidOrder(array $lines): Order
    {
        $o = Order::create(['number' => Order::nextNumber(), 'client_id' => $this->client->id, 'status' => OrderStatus::Paid,
            'start_date' => '2026-12-04', 'end_date' => '2026-12-06']);
        foreach ($lines as [$id, $q]) {
            $o->lines()->create(['article_id' => $id, 'quantity' => $q, 'unit_price' => 10]);
        }

        return $o;
    }

    private function scan(string $mode, string $code, ?Order $o = null)
    {
        return $this->actingAs($this->user)->postJson(route('scan.scan', [$o ?? $this->order, $mode]), ['code' => $code]);
    }

    public function test_full_out_and_in_flow(): void
    {
        [$c1, $c2, $c3] = $this->chair->units->take(3)->all();
        $t1 = $this->table->units->first();

        $this->actingAs($this->user)->get(route('scan.show', [$this->order, 'salida']))->assertOk()->assertSee('Activar cámara');
        $this->actingAs($this->user)->get(route('scan.show', [$this->order, 'entrada']))->assertForbidden();

        // Por la URL del QR, por el código y repetido
        $this->scan('salida', $c1->qrUrl())->assertJson(['level' => 'ok']);
        $this->scan('salida', strtolower($c2->code))->assertJson(['level' => 'ok']);
        $this->scan('salida', $c2->code)->assertJson(['level' => 'warn']);
        $this->scan('salida', $c3->code)->assertJson(['level' => 'warn']); // sobra
        $this->scan('salida', 'XXX')->assertJson(['level' => 'error']);
        $this->assertSame(UnitStatus::Rented, $c1->fresh()->status);

        // Quitar la que sobra
        $this->actingAs($this->user)->postJson(route('scan.update', [$this->order, 'salida', $c3]))->assertOk();
        $this->assertSame(UnitStatus::Available, $c3->fresh()->status);

        $this->scan('salida', $t1->qr_token)->assertJson(['level' => 'ok']);
        $this->actingAs($this->user)->post(route('scan.finish', [$this->order, 'salida']))->assertRedirect(route('orders.show', $this->order));
        $this->assertSame(OrderStatus::Delivered, $this->order->fresh()->status);

        // Entrada: una devuelta, una rota, una perdida
        $this->scan('entrada', $c3->code)->assertJson(['level' => 'error']); // no salió
        $this->scan('entrada', $c1->qrUrl())->assertJson(['level' => 'ok']);
        $this->actingAs($this->user)->postJson(route('scan.update', [$this->order, 'entrada', $c2]), ['status' => 'damaged'])->assertOk();
        $this->actingAs($this->user)->postJson(route('scan.update', [$this->order, 'entrada', $t1]), ['status' => 'lost'])->assertOk();
        $this->assertSame(UnitStatus::Available, $c1->fresh()->status);
        $this->assertSame(UnitStatus::Maintenance, $c2->fresh()->status);
        $this->assertSame(UnitStatus::Retired, $t1->fresh()->status);

        $this->actingAs($this->user)->post(route('scan.finish', [$this->order, 'entrada']));
        $this->assertSame(OrderStatus::Returned, $this->order->fresh()->status);
        $this->actingAs($this->user)->get(route('orders.show', $this->order))->assertOk()
            ->assertSee('Incidencias a la vuelta')->assertSee('Reposición de lo perdido');
    }

    public function test_unit_out_with_another_order_cannot_leave_and_cancel_releases(): void
    {
        $c1 = $this->chair->units->first();
        $this->scan('salida', $c1->code)->assertJson(['level' => 'ok']);

        $other = $this->paidOrder([[$this->chair->id, 1]]);
        $this->scan('salida', $c1->code, $other)->assertJson(['level' => 'error']);

        $this->actingAs($this->user)->post(route('orders.transition', $this->order), ['status' => 'cancelled']);
        $this->assertSame(UnitStatus::Available, $c1->fresh()->status);
        $this->scan('salida', $c1->code, $other)->assertJson(['level' => 'ok']);
    }
}
