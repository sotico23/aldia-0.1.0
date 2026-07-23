<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = [
            'pedidos',
            'inventarios',
            'cambio_productos',
            'gasto_proyectos',
            'incidents',
            'monitored_sites',
            'appointments',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'owner_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('owner_id')->nullable()->index();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'pedidos',
            'inventarios',
            'cambio_productos',
            'gasto_proyectos',
            'incidents',
            'monitored_sites',
            'appointments',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'owner_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('owner_id');
                });
            }
        }
    }
};
