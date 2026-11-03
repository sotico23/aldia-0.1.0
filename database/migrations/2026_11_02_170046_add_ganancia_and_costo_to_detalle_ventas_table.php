<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->decimal('costo_unitario', 12, 2)->nullable()->after('precio_unitario');
            $table->decimal('ganancia', 12, 2)->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropColumn(['costo_unitario', 'ganancia']);
        });
    }
};
