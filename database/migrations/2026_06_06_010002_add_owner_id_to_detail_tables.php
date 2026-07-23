<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add owner_id to detalle_ventas and backfill from parent ventas
        if (Schema::hasTable('detalle_ventas') && ! Schema::hasColumn('detalle_ventas', 'owner_id')) {
            Schema::table('detalle_ventas', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->index('owner_id');
            });

            DB::statement('UPDATE detalle_ventas SET owner_id = (SELECT ventas.owner_id FROM ventas WHERE ventas.id = detalle_ventas.venta_id) WHERE EXISTS (SELECT 1 FROM ventas WHERE ventas.id = detalle_ventas.venta_id)');
        }

        // Add owner_id to detalle_compras and backfill from parent compras
        if (Schema::hasTable('detalle_compras') && ! Schema::hasColumn('detalle_compras', 'owner_id')) {
            Schema::table('detalle_compras', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->index('owner_id');
            });

            DB::statement('UPDATE detalle_compras SET owner_id = (SELECT compras.owner_id FROM compras WHERE compras.id = detalle_compras.compra_id) WHERE EXISTS (SELECT 1 FROM compras WHERE compras.id = detalle_compras.compra_id)');
        }
    }

    public function down(): void
    {
        $tables = ['detalle_ventas', 'detalle_compras'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'owner_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['owner_id']);
                    $table->dropIndex(['owner_id']);
                    $table->dropColumn('owner_id');
                });
            }
        }
    }
};
