<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caf_folios', function (Blueprint $table) {
            $table->id();

            // Multi-tenant
            $table->unsignedBigInteger('owner_id')->index();

            // Tipo de documento SII (33=Factura, 34=Factura exenta, 39=Boleta, etc.)
            $table->unsignedSmallInteger('tipo_documento');

            // Rango de folios autorizado por el SII
            $table->unsignedInteger('folio_desde');
            $table->unsignedInteger('folio_hasta');
            $table->unsignedInteger('folio_siguiente'); // puntero al próximo disponible

            // XML completo del CAF tal como lo entrega el SII
            $table->longText('xml_caf');

            // Fecha de vencimiento del CAF (el SII las vence en 6 meses)
            $table->date('fecha_vencimiento')->nullable();

            // Ambiente: 'certificacion' | 'produccion'
            $table->string('ambiente', 20)->default('certificacion');

            $table->boolean('agotado')->default(false);
            $table->timestamps();

            $table->index(['owner_id', 'tipo_documento', 'agotado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caf_folios');
    }
};
