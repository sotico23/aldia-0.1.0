<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dte_documentos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('owner_id')->index();

            // Referencia al documento de origen en el ERP
            $table->string('modelo_origen', 60)->nullable(); // 'Factura', 'Venta', etc.
            $table->unsignedBigInteger('origen_id')->nullable();

            // Datos SII
            $table->unsignedSmallInteger('tipo_documento'); // 33, 34, 39, 61…
            $table->unsignedInteger('folio');
            $table->unsignedBigInteger('caf_folio_id')->nullable();

            // RUTs
            $table->string('rut_emisor', 15);
            $table->string('rut_receptor', 15);
            $table->string('razon_social_receptor', 120);
            $table->string('giro_receptor', 80)->nullable();
            $table->string('direccion_receptor', 160)->nullable();
            $table->string('comuna_receptor', 60)->nullable();

            // Montos (CLP – enteros)
            $table->unsignedBigInteger('monto_neto')->default(0);
            $table->unsignedBigInteger('monto_iva')->default(0);
            $table->unsignedBigInteger('monto_exento')->default(0);
            $table->unsignedBigInteger('monto_total');

            // XML firmado y timbre
            $table->longText('xml_sin_firma')->nullable();
            $table->longText('xml_firmado')->nullable();
            $table->text('ted')->nullable(); // Timbre Electrónico DTE (para PDF)

            // Estado del documento
            // borrador | firmado | enviado | aceptado | rechazado | reparado
            $table->string('estado', 30)->default('borrador');
            $table->string('estado_sii', 100)->nullable(); // respuesta textual del SII
            $table->string('track_id', 30)->nullable(); // ID de seguimiento SII

            // Ambiente
            $table->string('ambiente', 20)->default('certificacion');

            $table->timestamps();

            $table->unique(['owner_id', 'tipo_documento', 'folio']);
            $table->index(['owner_id', 'estado']);
            $table->index(['modelo_origen', 'origen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dte_documentos');
    }
};
