<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dte_envios', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('owner_id')->index();

            // El sobre puede contener múltiples documentos (EnvioDTE)
            $table->string('rut_emisor', 15);
            $table->string('rut_receptor_sii', 15)->default('60803000-K'); // RUT del SII

            // XML del sobre firmado enviado al SII
            $table->longText('xml_sobre')->nullable();

            // Respuesta del SII
            $table->string('track_id', 30)->nullable();
            $table->string('estado', 30)->default('pendiente');
            // pendiente | enviado | aceptado | aceptado_con_reparos | rechazado | error

            $table->text('respuesta_sii')->nullable(); // texto plano de la respuesta SOAP
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('respuesta_at')->nullable();

            $table->string('ambiente', 20)->default('certificacion');

            $table->timestamps();

            $table->index(['owner_id', 'estado']);
            $table->index('track_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_envios');
    }
};
