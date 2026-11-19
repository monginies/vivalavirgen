<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('event_start_time', 5)->nullable()->after('event_date')->comment('Hora de inicio del evento (HH:MM)');
            $table->string('event_end_time', 5)->nullable()->after('event_start_time')->comment('Hora de fin del evento (HH:MM)');
            $table->string('event_wedding', 150)->nullable()->after('event_end_time')->comment('Wedding planner; «No hay» si no hay');
            $table->string('event_catering', 150)->nullable()->after('event_wedding')->comment('Catering; «No hay» si no hay');
            $table->string('event_florist', 150)->nullable()->after('event_catering')->comment('Florista; «No hay» si no hay');
            $table->string('referral', 150)->nullable()->after('event_florist')->comment('Quién recomendó al cliente; «No hay» si nadie');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn([
            'event_start_time', 'event_end_time', 'event_wedding', 'event_catering', 'event_florist', 'referral',
        ]));
    }
};
