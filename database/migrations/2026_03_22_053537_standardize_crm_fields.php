<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'cliente') && ! Schema::hasColumn('tickets', 'cliente_nombre')) {
                $table->renameColumn('cliente', 'cliente_nombre');
            }
            if (Schema::hasColumn('tickets', 'producto') && ! Schema::hasColumn('tickets', 'producto_nombre')) {
                $table->renameColumn('producto', 'producto_nombre');
            }
        });

        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'cliente') && ! Schema::hasColumn('pedidos', 'cliente_nombre')) {
                $table->renameColumn('cliente', 'cliente_nombre');
            }
        });

        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'numero_factura') && ! Schema::hasColumn('ventas', 'numero')) {
                $table->renameColumn('numero_factura', 'numero');
            }
        });

        Schema::table('compras', function (Blueprint $table) {
            if (Schema::hasColumn('compras', 'numero_factura') && ! Schema::hasColumn('compras', 'numero')) {
                $table->renameColumn('numero_factura', 'numero');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'cliente_nombre')) {
                $table->renameColumn('cliente_nombre', 'cliente');
            }
            if (Schema::hasColumn('tickets', 'producto_nombre')) {
                $table->renameColumn('producto_nombre', 'producto');
            }
        });

        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'cliente_nombre')) {
                $table->renameColumn('cliente_nombre', 'cliente');
            }
        });

        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'numero') && ! Schema::hasColumn('ventas', 'numero_factura')) {
                $table->renameColumn('numero', 'numero_factura');
            }
        });

        Schema::table('compras', function (Blueprint $table) {
            if (Schema::hasColumn('compras', 'numero') && ! Schema::hasColumn('compras', 'numero_factura')) {
                $table->renameColumn('numero', 'numero_factura');
            }
        });
    }
};
