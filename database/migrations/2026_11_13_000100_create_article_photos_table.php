<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('path')->comment('Imagen grande (máx. 1600 px) en el disco public');
            $table->string('thumb_path')->comment('Miniatura (máx. 480 px)');
            $table->unsignedInteger('position')->default(0)->comment('0 = foto principal');
            $table->timestamps();
            $table->index(['article_id', 'position']);
        });

        // La foto única que pudiera haber pasa a ser la principal
        foreach (DB::table('articles')->whereNotNull('photo_path')->get(['id', 'photo_path']) as $a) {
            DB::table('article_photos')->insert([
                'article_id' => $a->id, 'path' => $a->photo_path, 'thumb_path' => $a->photo_path,
                'position' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('article_photos');
    }
};
