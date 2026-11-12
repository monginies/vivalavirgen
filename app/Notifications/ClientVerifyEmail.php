<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class ClientVerifyEmail extends VerifyEmail
{
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute('portal.verification.verify', now()->addDays(3), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirma tu email · Viva la Virgen')
            ->greeting('¡Hola!')
            ->line('Gracias por crear tu cuenta en Viva la Virgen. Confirma tu dirección de email con el botón:')
            ->action('Confirmar mi email', $url)
            ->line('Después revisaremos tus datos y te avisaremos cuando tu cuenta esté activa para hacer pedidos.')
            ->line('Si no has creado ninguna cuenta, ignora este mensaje.');
    }
}
