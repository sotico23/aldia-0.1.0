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
            $table->renameColumn('factor_conversion', 'contenido_por_unidad');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('contenido_por_unidad', 15, 2)->change();
            $table->decimal('peso_base', 15, 2)->change();
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->renameColumn('total_kilogramos', 'subtotal_metrica');
            $table->dropColumn('total_litros');
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->decimal('subtotal_metrica', 15, 2)->change();
        });

        Schema::table('entrega_items', function (Blueprint $table) {
            $table->renameColumn('total_kilogramos', 'subtotal_metrica');
            $table->dropColumn('total_litros');
        });

        Schema::table('entrega_items', function (Blueprint $table) {
            $table->decimal('subtotal_metrica', 15, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entrega_items', function (Blueprint $table) {
            $table->decimal('subtotal_metrica', 15, 3)->change();
        });

        Schema::table('entrega_items', function (Blueprint $table) {
            $table->renameColumn('subtotal_metrica', 'total_kilogramos');
            $table->decimal('total_litros', 15, 3)->default(0);
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->decimal('subtotal_metrica', 15, 3)->change();
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->renameColumn('subtotal_metrica', 'total_kilogramos');
            $table->decimal('total_litros', 15, 3)->default(0);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('contenido_por_unidad', 15, 3)->change();
            $table->decimal('peso_base', 15, 3)->change();
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->renameColumn('contenido_por_unidad', 'factor_conversion');
        });
    }
};
