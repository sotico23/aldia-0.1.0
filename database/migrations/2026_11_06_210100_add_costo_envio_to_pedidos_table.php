<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Costo de envío cobrado al cliente y ganancia liquidada al
            // repartidor. Se congelan al momento de la entrega para que el
            // histórico de ganancias no cambie si luego se editan las tarifas.
            $table->decimal('costo_envio', 10, 2)->nullable()->after('total');
            $table->decimal('ganancia_repartidor', 10, 2)->nullable()->after('costo_envio');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['costo_envio', 'ganancia_repartidor']);
        });
    }
};
