<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Money;
use App\Support\Push;
use Illuminate\Console\Command;

/** Avisa al equipo (push) de las pre-reservas que caducan en las próximas 24 horas. Una vez por pedido. */
class ExpiryAlerts extends Command
{
    protected $signature = 'orders:expiry-alerts';

    protected $description = 'Avisa por notificación de las pre-reservas que caducan en menos de 24 horas';

    public function handle(): int
    {
        $orders = Order::with('client')
            ->whereIn('status', OrderStatus::expirable())
            ->whereNull('expiry_alerted_at')
            ->whereBetween('expires_at', [now(), now()->addDay()])
            ->get();

        foreach ($orders as $o) {
            $what = $o->status === OrderStatus::Requested ? 'sin aprobar' : 'sin pagar';
            Push::toOffice(
                "Caduca pronto: {$o->number}",
                "{$o->client->name} · {$what} · ".Money::eur($o->amountDue())." · caduca el {$o->expires_at->format('d/m H:i')}",
                route('orders.show', $o),
                'expiry-'.$o->id,
            );
            $o->forceFill(['expiry_alerted_at' => now()])->saveQuietly();
        }
        $this->info("Avisos enviados: {$orders->count()}");

        return self::SUCCESS;
    }
}
