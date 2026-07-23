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
        Schema::create('configuracion_siis', function (Blueprint $table) {
            $table->id();

            // Multi-tenant
            $table->unsignedBigInteger('owner_id')->unique();

            // Datos del Emisor
            $table->string('rut', 15);
            $table->string('razon_social', 150);
            $table->string('giro', 150);
            $table->unsignedInteger('acteco'); // Actividad Económica Principal

            // Dirección Casa Matriz
            $table->string('direccion', 150);
            $table->string('comuna', 60);
            $table->string('ciudad', 60);

            // Datos de la Resolución SII (para el XML)
            $table->unsignedInteger('resolucion_numero')->nullable();
            $table->date('resolucion_fecha')->nullable();

            // Configuración de Ambiente por Tenant
            $table->string('ambiente', 20)->default('certificacion');

            // Contacto Administrativo (opcional)
            $table->string('email_dte', 100)->nullable();
            $table->string('telefono', 20)->nullable();

            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_siis');
    }
};
