<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('proforma_sent_at')->nullable()->after('expires_at');
            $table->string('proforma_sent_to')->nullable()->after('proforma_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['proforma_sent_at', 'proforma_sent_to']));
    }
};
