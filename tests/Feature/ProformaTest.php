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
    }
}
