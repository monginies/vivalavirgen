<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use App\Support\Proforma;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProformaSent extends Notification
{
    public function __construct(public Order $order, public ?string $note = null) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $o = $this->order;
        $mail = (new MailMessage)
            ->subject("Proforma {$o->number} · Viva la Virgen")
            ->greeting('¡Hola!')
            ->line("Te enviamos la proforma de tu pedido **{$o->number}** para el ".($o->event_date ?? $o->start_date)->format('d/m/Y').'.');
        if ($this->note) {
            $mail->line($this->note);
        }
        $mail->line($o->deposit_amount > 0
            ? 'Subtotal '.Money::eur($o->total()).' (IVA incluido) + fianza '.Money::eur($o->deposit_amount).' = **total a pagar '.Money::eur($o->amountDue()).'**.'
            : 'Total a pagar: **'.Money::eur($o->amountDue()).'** (IVA incluido).');
        if ($o->expires_at && $o->status->isExpirable()) {
            $mail->line("El material queda reservado hasta el {$o->expires_at->format('d/m/Y')}. Para confirmar el pedido, realiza el pago antes de esa fecha indicando **{$o->number}** en el concepto.");
        }
        if ($o->source === 'web') {
            $mail->action('Ver mi pedido', route('portal.orders.show', $o->number));
        }

        return $mail
            ->line('Si tienes cualquier duda, responde a este email.')
            ->attachData(Proforma::pdf($o)->output(), Proforma::filename($o), ['mime' => 'application/pdf']);
    }
}
