<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\User;
use App\Notifications\ProformaSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProformaTest extends TestCase
{
    use RefreshDatabase;

    private function order(OrderStatus $status = OrderStatus::Accepted): Order
    {
        $cat = Category::create(['name' => 'Sillas', 'code' => 'SIL']);
        $a = Article::create(['category_id' => $cat->id, 'reference' => 'SIL-0001', 'name' => 'Silla Crossback', 'dimensions' => '46 × 52 × 88 cm', 'rental_price' => 4.5, 'replacement_price' => 60, 'active' => true]);
        $client = Client::create(['type' => 'company', 'name' => 'Bodas Bonitas SL', 'tax_id' => 'B12345678', 'email' => 'cliente@example.com']);
        $o = Order::create(['number' => 'PED-2026-0007', 'client_id' => $client->id, 'status' => $status, 'event_date' => '2026-12-05', 'start_date' => '2026-12-04', 'end_date' => '2026-12-06',
            'destination_city' => 'Málaga', 'distance_km' => 30, 'trucks' => 1, 'transport_amount' => 60, 'discount_percent' => 10, 'deposit_amount' => 150, 'vat_rate' => 21, 'expires_at' => now()->addDays(3)]);
        $o->lines()->create(['article_id' => $a->id, 'quantity' => 100, 'unit_price' => 4.5]);

        return $o;
    }

    public function test_pdf_and_send_to_client(): void
    {
        Notification::fake();
        $o = $this->order();
        $staff = User::factory()->create();

        $res = $this->actingAs($staff)->get(route('orders.proforma', $o))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());

        $this->actingAs($staff)->get(route('orders.show', $o))->assertSee('Enviar proforma');
        $this->actingAs($staff)->post(route('orders.proforma.send', $o), ['email' => 'cliente@example.com', 'note' => 'Incluye montaje'])->assertSessionHas('status');

        $o->refresh();
        $this->assertSame(OrderStatus::ProformaSent, $o->status);
        $this->assertNotNull($o->proforma_sent_at);
        Notification::assertSentTo(new AnonymousNotifiable, ProformaSent::class, function ($n, $channels, $notifiable) {
            $mail = $n->toMail($notifiable);

            return $notifiable->routes['mail'] === 'cliente@example.com' && count($mail->rawAttachments) === 1
                && str_contains(implode(' ', $mail->introLines), 'Incluye montaje');
        });
    }

    public function test_assembly_is_set_when_sending_and_has_vat_but_no_discount(): void
    {
        Notification::fake();
        $o = $this->order();
        $staff = User::factory()->create();
        // Material 450 − 10 % = 405; transporte 60; base 465
        $this->assertEquals(465, $o->taxableBase());

        $this->actingAs($staff)->get(route('orders.show', $o))->assertSee('name="assembly_amount"', false)->assertSee('¿Seguro que este pedido no lleva montaje?');
        $this->actingAs($staff)->post(route('orders.proforma.send', $o), ['email' => 'cliente@example.com', 'assembly_amount' => '120'])->assertSessionHas('status');

        $o->refresh();
        $this->assertEquals(120, $o->assembly_amount);
        $this->assertEquals(585, $o->taxableBase()); // + 120 de montaje, sin descuento
        $this->assertEquals(122.85, $o->vatAmount()); // 21 % de 585
        $this->assertEquals(857.85, $o->amountDue()); // 707,85 + 150 de fianza
        $this->actingAs($staff)->get(route('orders.show', $o))->assertSee('Montaje')->assertSee('120,00 €');

        // Ya pagado: el montaje no se toca al reenviar
        $o->update(['status' => OrderStatus::Paid]);
        $this->actingAs($staff)->get(route('orders.show', $o))->assertDontSee('name="assembly_amount"', false);
        $this->actingAs($staff)->post(route('orders.proforma.send', $o), ['email' => 'cliente@example.com', 'assembly_amount' => '999']);
        $this->assertEquals(120, $o->fresh()->assembly_amount);

        // El cliente lo ve en su pedido; sin montaje, la línea no aparece
        $client = $o->client;
        $client->forceFill(['password' => 'secreta123', 'email_verified_at' => now(), 'approved_at' => now()])->save();
        $this->actingAs($client, 'client')->get(route('portal.orders.show', $o->number))->assertSee('Montaje');
        $o->update(['assembly_amount' => 0]);
        $this->actingAs($client, 'client')->get(route('portal.orders.show', $o->number))->assertDontSee('Montaje');
    }

    public function test_commission_is_highlighted_for_the_client(): void
    {
        $o = $this->order(OrderStatus::ProformaSent);
        $o->update(['proforma_sent_at' => now()]);
        $client = $o->client;
        $client->forceFill(['profession' => 'wedding', 'password' => 'secreta123', 'email_verified_at' => now(), 'approved_at' => now()])->save();
        $html = fn () => view('pdf.proforma', ['order' => $o->fresh(['client', 'lines.article']), 'company' => array_fill_keys(['name', 'tax_id', 'address', 'phone', 'email', 'iban'], null), 'terms' => null])->render();

        // Profesional: «Tu comisión», aparte, y el material ya con ella aplicada (450 − 45)
        $this->assertStringContainsString('class="commission"', $html());
        $this->assertStringContainsString('Tu comisión', $html());
        $this->assertStringContainsString('405,00 €', $html());
        $this->actingAs($client, 'client')->get(route('portal.orders.show', $o->number))
            ->assertSeeInOrder(['Tu comisión', '10 %', '45,00 €', 'Material', '405,00 €']);
        $this->actingAs($client, 'client')->get(route('portal.orders.proforma', $o->number))->assertOk();

        // Particular: «Tu descuento»; sin descuento, no hay recuadro
        $client->update(['type' => 'individual', 'profession' => null]);
        $this->assertStringContainsString('Tu descuento', $html());
        $o->update(['discount_percent' => 0]);
        $this->assertStringNotContainsString('class="commission"', $html());

        // En el backoffice se sigue viendo como una línea más
        $o->update(['discount_percent' => 10]);
        $this->actingAs(User::factory()->create())->get(route('orders.show', $o))->assertSee('Descuento / comisión 10 %')->assertDontSee('Tu descuento');
    }

    public function test_client_downloads_own_proforma_only_after_sent(): void
    {
        $o = $this->order(OrderStatus::ProformaSent);
        $client = $o->client;
        $client->forceFill(['password' => 'secreta123', 'email_verified_at' => now(), 'approved_at' => now()])->save();

        $this->actingAs($client, 'client')->get(route('portal.orders.proforma', $o->number))->assertNotFound();
        $o->update(['proforma_sent_at' => now()]);
        $this->actingAs($client, 'client')->get(route('portal.orders.show', $o->number))->assertSee('Descargar proforma');
        $this->actingAs($client, 'client')->get(route('portal.orders.proforma', $o->number))->assertOk()->assertDownload('Proforma-PED-2026-0007.pdf');
    }

    public function test_cannot_send_for_cancelled_or_overdue(): void
    {
        $o = $this->order(OrderStatus::Cancelled);
        $this->actingAs(User::factory()->create())->post(route('orders.proforma.send', $o), ['email' => 'x@example.com'])->assertForbidden();
        $o->update(['status' => OrderStatus::Accepted, 'expires_at' => now()->subHour()]);
        $this->actingAs(User::factory()->create())->post(route('orders.proforma.send', $o), ['email' => 'x@example.com'])->assertSessionHas('error');

        // Pendiente de aprobar: aún no se puede enviar
        $o->update(['status' => OrderStatus::Requested, 'expires_at' => now()->addDay()]);
        $u = User::factory()->create();
        $this->actingAs($u)->post(route('orders.proforma.send', $o), ['email' => 'x@example.com'])->assertForbidden();
        $this->actingAs($u)->get(route('orders.show', $o))->assertOk()->assertDontSee('Enviar proforma')->assertSee('cuando apruebes el pedido');
    }
}
