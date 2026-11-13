<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Productos combinados: un artículo formado por varios artículos con unidades propias. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->boolean('is_bundle')->default(false)->after('category_id')->comment('Producto combinado: sin unidades propias, reserva sus piezas');
        });

        Schema::create('article_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('articles')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1)->comment('Piezas de este artículo en cada combinado');
            $table->unique(['bundle_id', 'component_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_components');
        Schema::table('articles', fn (Blueprint $table) => $table->dropColumn('is_bundle'));
    }
};
