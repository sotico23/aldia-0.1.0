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
            $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->enum('metodo_pago', ['efectivo', 'tarjeta', 'transferencia', 'otro'])->default('efectivo')->after('total');
            $table->enum('tipo_documento', ['boleta', 'factura', 'nota_credito', 'cotizacion'])->default('boleta')->after('metodo_pago');
            $table->boolean('es_pos')->default(true)->after('tipo_documento');
            $table->string('numero_factura')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'metodo_pago', 'tipo_documento', 'es_pos']);
            $table->string('numero_factura')->nullable(false)->change();
        });
    }
};
