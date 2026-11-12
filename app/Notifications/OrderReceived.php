<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirmación al cliente de que hemos recibido su solicitud de pedido. */
class OrderReceived extends Notification
{
    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $o = $this->order->loadMissing('lines.article');
        $mail = (new MailMessage)
            ->subject("Hemos recibido tu pedido {$o->number} · Viva la Virgen")
            ->greeting('¡Gracias por tu pedido!')
            ->line("Tu solicitud **{$o->number}** para el {$o->start_date->format('d/m/Y')} – {$o->end_date->format('d/m/Y')} ha quedado registrada y el material está **pre-reservado** hasta el {$o->expires_at?->format('d/m/Y H:i')}.")
            ->line('Material:');
        foreach ($o->lines as $l) {
            $mail->line("· {$l->quantity} × {$l->article->name}");
        }

        return $mail
            ->line('Total estimado: **'.Money::eur($o->total()).'** (IVA incluido) más '.Money::eur($o->deposit_amount).' de fianza.')
            ->line('Revisaremos el pedido y te enviaremos la proforma para confirmarlo.')
            ->action('Ver mi pedido', route('portal.orders.show', $o->number));
    }
}
