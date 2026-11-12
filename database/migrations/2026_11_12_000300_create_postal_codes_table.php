<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_codes', function (Blueprint $table) {
            $table->id();
            $table->char('code', 5)->index();
            $table->string('place')->comment('Localidad o pedanía');
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->decimal('lat', 8, 5);
            $table->decimal('lon', 8, 5);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('destination_postal_code', 5)->nullable()->after('destination_city');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('destination_postal_code'));
        Schema::dropIfExists('postal_codes');
    }
};
