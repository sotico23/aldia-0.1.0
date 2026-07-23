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
        Schema::table('ventas', function (Blueprint $table) {
            $table->boolean('incluye_iva')->default(true)->after('total');
            $table->string('tipo_descuento')->default('monto')->after('incluye_iva');
            $table->decimal('valor_descuento', 15, 2)->default(0)->after('tipo_descuento');
            $table->decimal('monto_descuento', 15, 2)->default(0)->after('valor_descuento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['incluye_iva', 'tipo_descuento', 'valor_descuento', 'monto_descuento']);
        });
    }
};
