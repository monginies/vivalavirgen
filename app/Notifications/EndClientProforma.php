<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use App\Support\Proforma;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Proforma a precio de tarifa que un profesional envía a su cliente desde «Mi cuenta».
 * Sale desde la cuenta de la empresa, con copia al profesional; las respuestas le llegan a él.
 */
class EndClientProforma extends Notification
{
    /** @param  array{name: string, email?: ?string, tax_id?: ?string, address?: ?string}  $endClient */
    public function __construct(public Order $order, public array $endClient) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $o = Proforma::atListPrice($this->order);
        $pro = $o->client;
        $proName = $pro->trade_name ?: $pro->name;

        $mail = (new MailMessage)
            ->subject("Proforma {$o->number} · Viva la Virgen")
            ->greeting("Hola, {$this->endClient['name']}:")
            ->line("**{$proName}** nos ha pedido que te enviemos la proforma del mobiliario para tu evento del ".($o->event_date ?? $o->start_date)->format('d/m/Y').'.')
            ->line($o->deposit_amount > 0
                ? 'Total '.Money::eur($o->total()).' (IVA incluido) + fianza '.Money::eur($o->deposit_amount).' = **total a pagar '.Money::eur($o->amountDue()).'**.'
                : 'Total a pagar: **'.Money::eur($o->amountDue()).'** (IVA incluido).');
        if ($o->expires_at && $o->status->isExpirable()) {
            $mail->line("El material queda reservado hasta el {$o->expires_at->format('d/m/Y')}. Para confirmarlo, realiza el pago por transferencia antes de esa fecha indicando **{$o->number}** en el concepto.");
        }
        if ($pro->email) {
            $mail->cc($pro->email, $proName)->replyTo($pro->email, $proName);
        }

        return $mail
            ->line("Si tienes cualquier duda, responde a este email y le llegará a {$proName}.")
            ->attachData(Proforma::forEndClient($this->order, $this->endClient)->output(), Proforma::filename($o), ['mime' => 'application/pdf']);
    }
}
