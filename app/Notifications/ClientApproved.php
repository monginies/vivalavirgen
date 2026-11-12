<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientApproved extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu cuenta ya está activa · Viva la Virgen')
            ->greeting('¡Hola, '.($notifiable->contact_name ?: $notifiable->name).'!')
            ->line('Hemos revisado tus datos y tu cuenta ya está activa. Desde ahora puedes ver tus precios y solicitar pedidos en nuestra web.')
            ->action('Ir al catálogo', route('portal.home'));
    }
}
