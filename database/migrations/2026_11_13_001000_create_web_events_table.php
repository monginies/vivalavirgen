<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Estadísticas propias de la web: sin IP ni cookies de terceros. */
    public function up(): void
    {
        Schema::create('web_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);                 // view, search, cart_add, checkout, register
            $table->char('visitor', 16);                // identificador aleatorio de la visita (sesión)
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path', 255)->nullable();
            $table->string('term', 100)->nullable();    // texto buscado
            $table->unsignedInteger('value')->nullable(); // nº de resultados, unidades añadidas, id del pedido…
            $table->string('source', 60)->nullable();   // procedencia de la visita
            $table->string('device', 10)->nullable();   // movil, tablet, ordenador
            $table->timestamp('created_at')->useCurrent();
            $table->index(['type', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_events');
    }
};
