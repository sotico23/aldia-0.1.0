<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->foreignId('repartidor_id')->nullable()->after('cliente_id')->constrained('users')->nullOnDelete();
            $table->decimal('destino_lat', 10, 7)->nullable()->after('direccion_cliente');
            $table->decimal('destino_lng', 10, 7)->nullable()->after('destino_lat');
            $table->decimal('distancia_km', 6, 2)->nullable()->after('destino_lng');
            $table->unsignedSmallInteger('pool_reenvios')->default(0)->after('distancia_km');
            $table->timestamp('pool_entrada_at')->nullable()->after('pool_reenvios');
            $table->timestamp('hora_aceptado')->nullable()->after('pool_entrada_at');
            $table->timestamp('hora_recogido')->nullable()->after('hora_aceptado');
            $table->timestamp('hora_entregado')->nullable()->after('hora_recogido');

            $table->index(['owner_id', 'estado', 'repartidor_id'], 'pedidos_pool_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropIndex('pedidos_pool_idx');
            $table->dropColumn([
                'repartidor_id',
                'destino_lat',
                'destino_lng',
                'distancia_km',
                'pool_reenvios',
                'pool_entrada_at',
                'hora_aceptado',
                'hora_recogido',
                'hora_entregado',
            ]);
        });
    }
};
