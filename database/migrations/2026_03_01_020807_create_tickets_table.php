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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('cliente')->nullable();
            $table->string('producto')->nullable();
            $table->string('prioridad')->default('media'); // baja, media, alta, critica
            $table->string('estado')->default('abierto'); // abierto, en_proceso, pendiente, resuelto, cerrado
            $table->string('categoria')->nullable();
            $table->string('asignado_a')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
