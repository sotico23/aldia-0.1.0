<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Movimientos ──
        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('producto');
            $table->foreignId('almacen_origen_id')->nullable()->after('almacen_origen');
            $table->foreignId('almacen_destino_id')->nullable()->after('almacen_destino');
        });

        DB::statement('UPDATE movimientos SET producto_id = (SELECT p.id FROM productos p WHERE p.nombre = movimientos.producto) WHERE EXISTS (SELECT 1 FROM productos p WHERE p.nombre = movimientos.producto)');
        DB::statement('UPDATE movimientos SET almacen_origen_id = (SELECT a.id FROM almacenes a WHERE a.nombre = movimientos.almacen_origen) WHERE EXISTS (SELECT 1 FROM almacenes a WHERE a.nombre = movimientos.almacen_origen)');
        DB::statement('UPDATE movimientos SET almacen_destino_id = (SELECT a.id FROM almacenes a WHERE a.nombre = movimientos.almacen_destino) WHERE EXISTS (SELECT 1 FROM almacenes a WHERE a.nombre = movimientos.almacen_destino)');

        Schema::table('movimientos', function (Blueprint $table) {
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('set null');
            $table->foreign('almacen_origen_id')->references('id')->on('almacenes')->onDelete('set null');
            $table->foreign('almacen_destino_id')->references('id')->on('almacenes')->onDelete('set null');
        });

        // ── ControlCalidad (calidad) ──
        Schema::table('calidad', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('producto');
            $table->foreignId('lote_id')->nullable()->after('lote');
        });

        DB::statement('UPDATE calidad SET producto_id = (SELECT p.id FROM productos p WHERE p.nombre = calidad.producto) WHERE EXISTS (SELECT 1 FROM productos p WHERE p.nombre = calidad.producto)');
        DB::statement('UPDATE calidad SET lote_id = (SELECT l.id FROM lotes l WHERE l.numero_lote = calidad.lote) WHERE EXISTS (SELECT 1 FROM lotes l WHERE l.numero_lote = calidad.lote)');

        Schema::table('calidad', function (Blueprint $table) {
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('set null');
            $table->foreign('lote_id')->references('id')->on('lotes')->onDelete('set null');
        });

        // ── Lote ──
        Schema::table('lotes', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('producto');
        });

        DB::statement('UPDATE lotes SET producto_id = (SELECT p.id FROM productos p WHERE p.nombre = lotes.producto) WHERE EXISTS (SELECT 1 FROM productos p WHERE p.nombre = lotes.producto)');

        Schema::table('lotes', function (Blueprint $table) {
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('set null');
        });

        // ── OrdenProduccion ──
        Schema::table('ordenes_produccion', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('producto');
        });

        DB::statement('UPDATE ordenes_produccion SET producto_id = (SELECT p.id FROM productos p WHERE p.nombre = ordenes_produccion.producto) WHERE EXISTS (SELECT 1 FROM productos p WHERE p.nombre = ordenes_produccion.producto)');

        Schema::table('ordenes_produccion', function (Blueprint $table) {
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('set null');
        });

        // ── Bom ──
        Schema::table('boms', function (Blueprint $table) {
            $table->foreignId('producto_final_id')->nullable()->after('producto_final');
        });

        DB::statement('UPDATE boms SET producto_final_id = (SELECT p.id FROM productos p WHERE p.nombre = boms.producto_final) WHERE EXISTS (SELECT 1 FROM productos p WHERE p.nombre = boms.producto_final)');

        Schema::table('boms', function (Blueprint $table) {
            $table->foreign('producto_final_id')->references('id')->on('productos')->onDelete('set null');
        });

        // ── Proyecto ──
        Schema::table('proyectos', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('cliente');
            $table->foreignId('responsable_id')->nullable()->after('responsable');
        });

        DB::statement('UPDATE proyectos SET cliente_id = (SELECT c.id FROM clientes c WHERE c.nombre = proyectos.cliente) WHERE EXISTS (SELECT 1 FROM clientes c WHERE c.nombre = proyectos.cliente)');
        DB::statement('UPDATE proyectos SET responsable_id = (SELECT u.id FROM users u WHERE u.name = proyectos.responsable) WHERE EXISTS (SELECT 1 FROM users u WHERE u.name = proyectos.responsable)');

        Schema::table('proyectos', function (Blueprint $table) {
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('set null');
            $table->foreign('responsable_id')->references('id')->on('users')->onDelete('set null');
        });

        // ── Entrega ──
        Schema::table('entregas', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('cliente');
        });

        DB::statement('UPDATE entregas SET cliente_id = (SELECT c.id FROM clientes c WHERE c.nombre = entregas.cliente) WHERE EXISTS (SELECT 1 FROM clientes c WHERE c.nombre = entregas.cliente)');

        Schema::table('entregas', function (Blueprint $table) {
            $table->foreign('cliente_id')->references('id')->on('clientes')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropForeign(['almacen_origen_id']);
            $table->dropForeign(['almacen_destino_id']);
            $table->dropColumn(['producto_id', 'almacen_origen_id', 'almacen_destino_id']);
        });

        Schema::table('calidad', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropForeign(['lote_id']);
            $table->dropColumn(['producto_id', 'lote_id']);
        });

        Schema::table('lotes', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropColumn(['producto_id']);
        });

        Schema::table('ordenes_produccion', function (Blueprint $table) {
            $table->dropForeign(['producto_id']);
            $table->dropColumn(['producto_id']);
        });

        Schema::table('boms', function (Blueprint $table) {
            $table->dropForeign(['producto_final_id']);
            $table->dropColumn(['producto_final_id']);
        });

        Schema::table('proyectos', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropForeign(['responsable_id']);
            $table->dropColumn(['cliente_id', 'responsable_id']);
        });

        Schema::table('entregas', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
            $table->dropColumn(['cliente_id']);
        });
    }
};
