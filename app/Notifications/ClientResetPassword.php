<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ClientResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return route('portal.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
    }

    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Cambiar tu contraseña · Viva la Virgen')
            ->greeting('¡Hola!')
            ->line('Hemos recibido una solicitud para cambiar la contraseña de tu cuenta.')
            ->action('Elegir una contraseña nueva', $url)
            ->line('El enlace caduca en 60 minutos. Si no lo has pedido tú, ignora este mensaje: tu contraseña no cambia.');
    }
}
