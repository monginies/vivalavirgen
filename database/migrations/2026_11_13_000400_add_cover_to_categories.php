<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('description')->comment('Foto de portada de la colección en la web');
            $table->unsignedSmallInteger('sort')->default(0)->after('cover_path')->comment('Orden en la web');
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $t) => $t->dropColumn(['cover_path', 'sort']));
    }
};
