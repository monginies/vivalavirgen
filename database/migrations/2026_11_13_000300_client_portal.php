<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
        DB::table('client_categories')->insert([
            ['name' => 'Particular', 'discount_percent' => 0, 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Wedding planner', 'discount_percent' => 10, 'sort' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Empresa', 'discount_percent' => 5, 'sort' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('client_category_id')->nullable()->after('type')->constrained()->nullOnDelete();
            $table->string('password')->nullable()->after('email')->comment('Acceso a la web de clientes');
            $table->rememberToken()->after('password');
            $table->timestamp('email_verified_at')->nullable()->after('remember_token');
            $table->timestamp('approved_at')->nullable()->after('email_verified_at')->comment('Sin aprobar no puede hacer pedidos en la web');
            $table->timestamp('privacy_accepted_at')->nullable()->after('approved_at');
            $table->string('source', 20)->default('backoffice')->after('privacy_accepted_at')->comment('backoffice | web');
            $table->unique('email');
        });

        Schema::create('client_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('source', 20)->default('backoffice')->after('status')->comment('backoffice | web');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::dropIfExists('client_password_reset_tokens');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropConstrainedForeignId('client_category_id');
            $table->dropColumn(['password', 'remember_token', 'email_verified_at', 'approved_at', 'privacy_accepted_at', 'source']);
        });
        Schema::dropIfExists('client_categories');
    }
};
