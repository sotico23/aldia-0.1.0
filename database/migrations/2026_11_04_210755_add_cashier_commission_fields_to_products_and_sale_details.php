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
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('comision_cajero_por_unidad', 12, 2)
                ->default(0)
                ->after('precio_venta');
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->decimal('comision_cajero', 12, 2)
                ->default(0)
                ->after('ganancia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropColumn('comision_cajero');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('comision_cajero_por_unidad');
        });
    }
};
