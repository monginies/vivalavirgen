<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('reference', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('dimensions')->nullable();
            $table->decimal('rental_price', 10, 2)->default(0)->comment('Precio de alquiler por evento/día, sin IVA');
            $table->decimal('replacement_price', 10, 2)->default(0)->comment('Coste de reposición por unidad');
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
