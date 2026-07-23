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
        Schema::create('conductores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('rut')->unique()->nullable();
            $table->string('licencia')->nullable();
            $table->dateTime('fecha_vencimiento_licencia')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->string('estado')->default('activo');
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conductores');
    }
};
