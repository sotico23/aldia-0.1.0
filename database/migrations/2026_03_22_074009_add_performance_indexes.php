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
        Schema::table('mensajes', function (Blueprint $table) {
            $table->index(['receiver_id', 'leido'], 'idx_mensajes_receiver_leido');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->index(['user_id', 'estado'], 'idx_pedidos_user_estado');
        });

        Schema::table('mensajes_conversacion', function (Blueprint $table) {
            $table->index(['receiver_id', 'leido'], 'idx_mensajes_conv_receiver_leido');
            $table->index(['conversacion_id', 'sender_id'], 'idx_mensajes_conv_conv_sender');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->index(['user_id', 'estado', 'fecha'], 'idx_ventas_user_estado_fecha');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->index(['proveedor_id', 'estado', 'fecha'], 'idx_compras_proveedor_estado_fecha');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->index(['user_id', 'activo'], 'idx_productos_user_activo');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->index(['user_id', 'activo'], 'idx_clientes_user_activo');
        });

        Schema::table('empleados', function (Blueprint $table) {
            $table->index(['user_id', 'estado'], 'idx_empleados_user_estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->dropIndex('idx_mensajes_receiver_leido');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_pedidos_user_estado');
        });

        Schema::table('mensajes_conversacion', function (Blueprint $table) {
            $table->dropIndex('idx_mensajes_conv_receiver_leido');
            $table->dropIndex('idx_mensajes_conv_conv_sender');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_user_estado_fecha');
        });

        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex('idx_compras_proveedor_estado_fecha');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('idx_productos_user_activo');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('idx_clientes_user_activo');
        });

        Schema::table('empleados', function (Blueprint $table) {
            $table->dropIndex('idx_empleados_user_estado');
        });
    }
};
