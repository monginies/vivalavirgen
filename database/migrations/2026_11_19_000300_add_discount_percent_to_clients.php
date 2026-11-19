<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('client_category_id')
                ->comment('Descuento / comisión propio; si es NULL se aplica el del tipo de cliente');
        });
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('discount_percent'));
    }
};
