<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('detalle_facturas') && ! Schema::hasColumn('detalle_facturas', 'owner_id')) {
            Schema::table('detalle_facturas', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->index('owner_id');
            });

            DB::statement('UPDATE detalle_facturas SET owner_id = (SELECT facturas.owner_id FROM facturas WHERE facturas.id = detalle_facturas.factura_id) WHERE EXISTS (SELECT 1 FROM facturas WHERE facturas.id = detalle_facturas.factura_id)');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('detalle_facturas') && Schema::hasColumn('detalle_facturas', 'owner_id')) {
            Schema::table('detalle_facturas', function (Blueprint $table) {
                $table->dropForeign(['owner_id']);
                $table->dropIndex(['owner_id']);
                $table->dropColumn('owner_id');
            });
        }
    }
};
