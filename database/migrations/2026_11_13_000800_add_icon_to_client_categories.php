<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_categories', function (Blueprint $table) {
            $table->string('icon', 20)->default('esparto')->after('name');
        });
        foreach (['Esparto' => 'esparto', 'Mimbre' => 'mimbre', 'Ratán' => 'ratan'] as $name => $icon) {
            DB::table('client_categories')->where('name', $name)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('client_categories', fn (Blueprint $t) => $t->dropColumn('icon'));
    }
};
