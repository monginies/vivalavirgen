<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al equipo de un pedido hecho desde la web. */
class NewWebOrder extends Notification
{
    public function __construct(public Order $order) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $o = $this->order->loadMissing('client', 'lines.article', 'venue');

        $mail = (new MailMessage)
            ->subject("Nuevo pedido web {$o->number} · {$o->client->name}")
            ->greeting("Pedido {$o->number} desde la web")
            ->line("Cliente: **{$o->client->name}**")
            ->line("Fechas: {$o->start_date->format('d/m/Y')} – {$o->end_date->format('d/m/Y')}".($o->event_date ? " (evento {$o->event_date->format('d/m/Y')})" : ''))
            ->line('Destino: '.($o->destination() ?: 'sin indicar'));
        foreach ($o->lines as $l) {
            $mail->line("· {$l->quantity} × {$l->article->reference} {$l->article->name}");
        }
        if (! $o->distance_km) {
            $mail->line('⚠ No se han podido calcular los km: revisa el transporte.');
        }

        return $mail
            ->line('Total estimado: '.Money::eur($o->total()))
            ->action('Abrir el pedido', route('orders.show', $o));
    }
}
