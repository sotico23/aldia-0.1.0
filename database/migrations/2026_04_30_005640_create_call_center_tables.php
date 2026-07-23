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
        Schema::create('llamadas_call_center', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Agente
            $table->foreignId('cliente_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('prospecto_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('tipo', ['entrante', 'saliente'])->default('saliente');
            $table->string('numero_telefono')->nullable();
            $table->enum('estado', ['completada', 'perdida', 'ocupado', 'no_contesta', 'equivocado'])->default('completada');
            $table->integer('duracion')->default(0); // en segundos
            $table->dateTime('fecha');
            $table->text('notas')->nullable();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('gestiones_call_center', function (Blueprint $table) {
            $table->id();
            $table->foreignId('llamada_id')->nullable()->constrained('llamadas_call_center')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Agente
            $table->foreignId('cliente_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('prospecto_id')->nullable()->constrained()->onDelete('set null');
            $table->text('comentario');
            $table->string('resultado'); // Compromiso Compra, Seguimiento, Cita, Rechazado
            $table->string('proxima_accion')->nullable();
            $table->dateTime('fecha_seguimiento')->nullable();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gestiones_call_center');
        Schema::dropIfExists('llamadas_call_center');
    }
};
