<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** Genera las claves VAPID para las notificaciones push (se copian al .env). */
class PushKeys extends Command
{
    protected $signature = 'push:keys';

    protected $description = 'Genera un par de claves VAPID para las notificaciones push';

    public function handle(): int
    {
        $k = VAPID::createVapidKeys();
        $this->line('VAPID_PUBLIC_KEY='.$k['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$k['privateKey']);

        return self::SUCCESS;
    }
}
