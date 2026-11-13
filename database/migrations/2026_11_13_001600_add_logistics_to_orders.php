<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Datos para la hoja de expedición (logística). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_time', 40)->nullable()->after('end_date')->comment('Hora o franja de entrega/montaje');
            $table->string('pickup_time', 40)->nullable()->after('delivery_time')->comment('Hora o franja de recogida/desmontaje');
            $table->string('site_contact_name')->nullable()->after('trucks')->comment('Persona de contacto en el lugar');
            $table->string('site_contact_phone', 40)->nullable()->after('site_contact_name');
            $table->text('logistics_notes')->nullable()->after('notes')->comment('Accesos, aparcamiento, montaje… (sale en la hoja de expedición)');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn([
            'delivery_time', 'pickup_time', 'site_contact_name', 'site_contact_phone', 'logistics_notes',
        ]));
    }
};
