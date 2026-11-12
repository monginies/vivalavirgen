<?php

namespace App\Notifications;

use App\Models\Client;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aviso al equipo: un cliente se ha registrado y ha confirmado su email. */
class NewClientRegistered extends Notification
{
    public function __construct(public Client $client) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $c = $this->client;

        return (new MailMessage)
            ->subject('Nuevo cliente pendiente de aprobar: '.$c->name)
            ->greeting('Nuevo registro en la web')
            ->line("**{$c->name}** ({$c->typeLabel()}".($c->tax_id ? ", {$c->tax_id}" : '').') ha creado una cuenta y confirmado su email.')
            ->line('Contacto: '.collect([$c->contact_name, $c->email, $c->phone])->filter()->join(' · '))
            ->action('Revisar y aprobar', route('clients.show', $c));
    }
}
