<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('trade_name')->nullable()->after('name')->comment('Nombre comercial');
        });
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('trade_name'));
    }
};
