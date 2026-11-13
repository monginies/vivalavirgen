<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Registro de cada ampliación de la pre-reserva. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days');
            $table->timestamp('previous_expires_at')->nullable();
            $table->timestamp('new_expires_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_extensions');
    }
};
