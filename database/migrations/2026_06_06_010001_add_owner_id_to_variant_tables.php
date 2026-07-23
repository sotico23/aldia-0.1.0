<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['sku_variantes', 'sku_variante_valores', 'variante_valores', 'producto_variantes'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'owner_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                    $table->index('owner_id');
                });
            }
        }
    }

    public function down(): void
    {
        $tables = ['sku_variantes', 'sku_variante_valores', 'variante_valores', 'producto_variantes'];

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
