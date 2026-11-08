<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Modalidad de Despacho (Internal vs Marketplace / Open).
 *
 * Agrega `dispatch_mode` a `pedidos` y `ventas`:
 *   - 'internal'    → Exclusivo para repartidores propios del tenant / empresa.
 *   - 'marketplace' → Abierto para la red global de repartidores independientes (freelancers tipo Uber/Rappi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'dispatch_mode')) {
                $table->string('dispatch_mode', 20)->default('internal')->after('estado');
                $table->index(['dispatch_mode', 'estado'], 'pedidos_dispatch_mode_idx');
            }
        });

        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'dispatch_mode')) {
                $table->string('dispatch_mode', 20)->default('internal')->after('has_delivery');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'dispatch_mode')) {
                $table->dropIndex('pedidos_dispatch_mode_idx');
                $table->dropColumn('dispatch_mode');
            }
        });

        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'dispatch_mode')) {
                $table->dropColumn('dispatch_mode');
            }
        });
    }
};
