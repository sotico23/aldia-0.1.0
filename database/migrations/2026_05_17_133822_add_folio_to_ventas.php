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
            $table->string('folio', 20)->nullable()->after('numero')->comment('Folio SII del documento');
            $table->integer('tipo_dte')->nullable()->after('folio')->comment('Tipo documento SII (33=Factura, 39=Boleta, etc)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['folio', 'tipo_dte']);
        });
    }
};
