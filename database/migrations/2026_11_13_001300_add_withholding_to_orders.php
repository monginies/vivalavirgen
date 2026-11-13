<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Retención (IRPF) de la proforma: se copia de Ajustes al crear el pedido, como el IVA. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('withholding_rate', 5, 2)->default(0)->after('vat_rate')
                ->comment('Retención en %, sobre la base imponible (material con descuento + transporte); no se aplica a la fianza');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('withholding_rate'));
    }
};
