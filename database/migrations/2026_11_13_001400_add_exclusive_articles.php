<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Artículos de alquiler exclusivo: solo los clientes autorizados pueden alquilarlos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('exclusive')->default(false)->after('is_bundle')
                ->comment('Alquiler exclusivo: para el resto de clientes aparece sin disponibilidad');
        });

        Schema::create('article_client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unique(['article_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_client');
        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn('exclusive'));
    }
};
