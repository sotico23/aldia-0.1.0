<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->constrained('users')->after('id');
            $table->index('owner_id');
        });

        // Poblar owner_id de registros existentes basado en el producto asociado
        DB::statement(
            'UPDATE inventarios i JOIN productos p ON p.id = i.producto_id SET i.owner_id = p.owner_id WHERE i.owner_id IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            $table->dropIndex(['owner_id']);
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
