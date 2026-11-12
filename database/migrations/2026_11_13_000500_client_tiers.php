<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Tipos de cliente definitivos: Esparto 10 %, Mimbre 20 %, Ratán 30 %. Sin tipo = precio base. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('clients')->update(['client_category_id' => null]);
        DB::table('client_categories')->delete();
        DB::table('client_categories')->insert([
            ['name' => 'Esparto', 'discount_percent' => 10, 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mimbre', 'discount_percent' => 20, 'sort' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ratán', 'discount_percent' => 30, 'sort' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void {}
};
