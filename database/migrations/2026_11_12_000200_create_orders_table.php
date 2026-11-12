<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique()->comment('PED-2026-0001');
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('requested')->index();
            $table->date('event_date')->nullable();
            $table->date('start_date')->comment('Entrega: desde este día el material está bloqueado');
            $table->date('end_date')->comment('Recogida: último día bloqueado');
            $table->string('destination_address')->nullable();
            $table->string('destination_city')->nullable();
            $table->decimal('distance_km', 8, 1)->nullable()->comment('Km de la base al destino (solo ida)');
            $table->unsignedTinyInteger('trucks')->default(1);
            $table->boolean('transport_manual')->default(false)->comment('Importe de transporte fijado a mano');
            $table->decimal('transport_amount', 10, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(21);
            $table->dateTime('expires_at')->nullable()->comment('Fin de la pre-reserva si no se paga');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['start_date', 'end_date']);
        });

        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2)->comment('Precio por unidad sin IVA en el momento del pedido');
            $table->timestamps();

            $table->unique(['order_id', 'article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
        Schema::dropIfExists('orders');
    }
};
