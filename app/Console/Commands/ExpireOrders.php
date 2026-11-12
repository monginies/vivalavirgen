<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class ExpireOrders extends Command
{
    protected $signature = 'orders:expire';

    protected $description = 'Marca como caducadas las pre-reservas que no se han pagado a tiempo';

    public function handle(): int
    {
        $n = Order::expireOverdue();
        $this->info("Pedidos caducados: {$n}");

        return self::SUCCESS;
    }
}
