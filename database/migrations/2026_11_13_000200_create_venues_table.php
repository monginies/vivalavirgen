<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nombre de la finca o lugar de celebración');
            $table->string('address')->nullable();
            $table->string('postal_code', 5)->nullable();
            $table->string('city')->nullable();
            $table->decimal('distance_km', 8, 1)->nullable()->comment('Km por carretera desde la base (solo ida)');
            $table->string('contact')->nullable()->comment('Persona o teléfono de la finca');
            $table->text('notes')->nullable()->comment('Accesos, horarios de montaje…');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('venue_id')->nullable()->after('event_date')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('venue_id'));
        Schema::dropIfExists('venues');
    }
};
