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
        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('iva_porcentaje', 5, 2)->default(19);
            $table->boolean('iva_incluido')->default(true);
            $table->string('descuento_tipo')->default('none'); // none, porcentaje, monto
            $table->decimal('descuento_valor', 12, 2)->default(0);
            $table->decimal('total_descuento', 12, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn(['iva_porcentaje', 'iva_incluido', 'descuento_tipo', 'descuento_valor', 'total_descuento']);
        });
    }
};
