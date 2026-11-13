<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Unidades físicas que salen con cada pedido (escaneo de QR) y cómo vuelven. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->timestamp('out_at');
            $table->foreignId('out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('in_at')->nullable();
            $table->foreignId('in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('return_status', 20)->nullable()->comment('ok, damaged (en reparación) o lost (perdida, de baja)');
            $table->unique(['order_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_units');
    }
};
