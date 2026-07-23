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
        Schema::create('calidad', function (Blueprint $table) {
            $table->id();
            $table->string('lote')->nullable();
            $table->string('producto')->nullable();
            $table->string('tipo')->nullable(); // inspeccion, prueba, certificacion
            $table->string('resultado')->nullable(); // aprobado, rechazado, pendiente
            $table->integer('cantidad_muestra')->default(0);
            $table->integer('cantidad_defectuosa')->default(0);
            $table->text('observaciones')->nullable();
            $table->date('fecha')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calidad');
    }
};
