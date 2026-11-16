<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('profession', 20)->nullable()->after('type')->comment('Categoría profesional: wedding, sitio, florista, catering');
        });

        // Quien marcó «Soy wedding planner» al registrarse pasa a la categoría Wedding
        $note = 'Al registrarse indicó que es wedding planner u organizador de eventos.';
        DB::table('clients')->where('notes', 'like', '%'.$note.'%')->update([
            'profession' => 'wedding',
            'notes' => DB::raw("NULLIF(TRIM(REPLACE(notes, '".$note."', '')), '')"),
        ]);
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn('profession'));
    }
};
