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
        Schema::create('carga_diarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vehiculo_id')->constrained('vehiculos')->onDelete('cascade');
            $table->foreignId('conductor_id')->constrained('conductores')->onDelete('cascade');
            $table->date('fecha');
            $table->string('estado')->default('pendiente'); // pendiente, en_ruta, cerrado
            $table->decimal('ventas_totales', 10, 2)->default(0);
            $table->decimal('devoluciones_totales', 10, 2)->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carga_diarias');
    }
};
