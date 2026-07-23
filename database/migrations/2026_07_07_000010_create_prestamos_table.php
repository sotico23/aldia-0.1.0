<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('empleado_id')->constrained('empleados')->onDelete('cascade');
            $table->string('tipo'); // prestamo, adelanto
            $table->decimal('monto_total', 12, 2);
            $table->decimal('monto_cuota', 12, 2);
            $table->integer('numero_cuotas');
            $table->integer('cuotas_pagadas')->default(0);
            $table->decimal('saldo_pendiente', 12, 2);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('frecuencia')->default('mensual'); // mensual, quincenal, semanal
            $table->string('estado')->default('activo'); // activo, pagado, cancelado
            $table->text('motivo')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};
