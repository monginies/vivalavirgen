<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('assembly_amount', 10, 2)->default(0)->after('transport_amount')
                ->comment('Montaje, sin IVA; lo fija el equipo antes de enviar la proforma; sin descuento');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('assembly_amount'));
    }
};
