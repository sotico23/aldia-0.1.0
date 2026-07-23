<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'clientes',
            'categorias',
            'productos',
            'ventas',
            'facturas',
            'cotizaciones',
            'oportunidades',
            'proveedors',
            'compras',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    // Decide where to place the owner_id for better schema organization
                    $afterColumn = Schema::hasColumn($tableName, 'user_id') ? 'user_id' : 'id';
                    $table->foreignId('owner_id')->nullable()->after($afterColumn)->constrained('users')->onDelete('cascade');
                });

                // Backfill owner_id from user_id if it exists and is likely the owner
                if (Schema::hasColumn($tableName, 'user_id')) {
                    DB::table($tableName)->whereNull('owner_id')->update([
                        'owner_id' => DB::raw('user_id'),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'clientes',
            'categorias',
            'productos',
            'ventas',
            'facturas',
            'cotizaciones',
            'oportunidades',
            'proveedors',
            'compras',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['owner_id']);
                    $table->dropColumn('owner_id');
                });
            }
        }
    }
};
