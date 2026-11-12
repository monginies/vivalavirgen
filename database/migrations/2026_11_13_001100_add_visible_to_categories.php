<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Las categorías salen en la web aunque aún no tengan artículos; esta casilla permite ocultarlas. */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('visible')->default(true)->after('sort');
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('visible'));
    }
};
