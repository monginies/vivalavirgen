<?php

namespace App\Support;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Envío de notificaciones push (Web Push con claves VAPID del .env). */
class Push
{
    /** En pruebas: Push::fake() guarda aquí lo que se habría enviado a oficina. */
    public static ?array $faked = null;

    public static function fake(): void
    {
        self::$faked = [];
    }

    public static function configured(): bool
    {
        return filled(config('services.webpush.public')) && filled(config('services.webpush.private'));
    }

    /** A todos los móviles activados por usuarios de oficina activos. Devuelve cuántos se enviaron. */
    public static function toOffice(string $title, string $body, string $url, ?string $tag = null): int
    {
        if (self::$faked !== null) {
            self::$faked[] = compact('title', 'body', 'url', 'tag');

            return 1;
        }
        $subs = PushSubscription::whereHas('user', fn ($q) => $q->where('active', true)->where('role', 'office'))->get();

        return self::send($subs, $title, $body, $url, $tag);
    }

    /** @param iterable<PushSubscription> $subs */
    public static function send(iterable $subs, string $title, string $body, string $url, ?string $tag = null): int
    {
        if (! self::configured()) {
            return 0;
        }
        $push = new WebPush(['VAPID' => [
            'subject' => config('services.webpush.subject'),
            'publicKey' => config('services.webpush.public'),
            'privateKey' => config('services.webpush.private'),
        ]], ['TTL' => 86400]);

        $byEndpoint = [];
        $payload = json_encode(compact('title', 'body', 'url', 'tag'), JSON_UNESCAPED_UNICODE);
        foreach ($subs as $s) {
            $byEndpoint[$s->endpoint] = $s;
            $push->queueNotification(Subscription::create([
                'endpoint' => $s->endpoint, 'publicKey' => $s->public_key, 'authToken' => $s->auth_token,
            ]), $payload);
        }

        $sent = 0;
        foreach ($push->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } elseif ($report->isSubscriptionExpired()) {
                $byEndpoint[$report->getEndpoint()]?->delete(); // el móvil ya no acepta avisos
            } else {
                report(new \RuntimeException('Push: '.$report->getReason()));
            }
        }

        return $sent;
    }
}
