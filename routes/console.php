<?php

use App\Models\WebEvent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Libera las pre-reservas que no se han pagado a tiempo
Schedule::command('orders:expire')->everyFifteenMinutes();

// Borra las estadísticas de la web más antiguas que el plazo de conservación
Schedule::command('model:prune', ['--model' => [WebEvent::class]])->dailyAt('04:10');
