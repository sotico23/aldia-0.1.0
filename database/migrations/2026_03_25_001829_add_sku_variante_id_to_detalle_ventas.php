<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->foreignId('sku_variante_id')->nullable()->constrained('sku_variantes')->nullOnDelete();
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('descuento', 12, 2)->nullable()->default(0)->after('iva');
        });
    }

    public function down(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropForeign(['sku_variante_id']);
            $table->dropColumn('sku_variante_id');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn('descuento');
        });
    }
};
