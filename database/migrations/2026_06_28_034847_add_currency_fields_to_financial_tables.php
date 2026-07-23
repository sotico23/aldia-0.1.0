<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['pedidos', 'cotizaciones', 'facturas', 'ventas'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    if (! Schema::hasColumn($table->getTable(), 'currency')) {
                        $table->string('currency', 10)->nullable()->after('estado');
                    }
                });
            }
        }

        // Remove default from transactions if possible, or leave it as it will be explicitly set.
        // Note: Changing default on an existing column requires doctrine/dbal, which might not be present.
        // Since we explicitly set currency in Transactions now, the default won't be hit usually.
    }

    public function down(): void
    {
        $tables = ['pedidos', 'cotizaciones', 'facturas', 'ventas'];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) {
                    if (Schema::hasColumn($table->getTable(), 'currency')) {
                        $table->dropColumn('currency');
                    }
                });
            }
        }
    }
};
