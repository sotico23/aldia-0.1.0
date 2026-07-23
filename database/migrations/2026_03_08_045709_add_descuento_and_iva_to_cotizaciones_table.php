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
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->decimal('iva_personalizado', 5, 2)->default(19.00);
            $table->string('descuento_tipo')->nullable(); // 'monto' o 'porcentaje'
            $table->decimal('descuento_monto', 12, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropColumn(['iva_personalizado', 'descuento_tipo', 'descuento_monto']);
        });
    }
};
