<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repartidores', function (Blueprint $table) {
            // Legajo digital del repartidor: cédula, licencia, permisos, etc.
            // Se guarda como JSON indexado por tipo de documento.
            $table->json('documentos')->nullable()->after('rejected_reason');
        });
    }

    public function down(): void
    {
        Schema::table('repartidores', function (Blueprint $table) {
            $table->dropColumn('documentos');
        });
    }
};
