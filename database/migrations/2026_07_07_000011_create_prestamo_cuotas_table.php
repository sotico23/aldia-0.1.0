<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamo_cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('prestamo_id')->constrained('prestamos')->onDelete('cascade');
            $table->integer('numero_cuota');
            $table->decimal('monto', 12, 2);
            $table->date('fecha_vencimiento');
            $table->date('fecha_pago')->nullable();
            $table->decimal('monto_pagado', 12, 2)->default(0);
            $table->string('estado')->default('pendiente'); // pendiente, pagada, vencida
            $table->string('metodo_pago')->nullable(); // efectivo, transferencia, nomina
            $table->string('referencia_pago')->nullable();
            $table->boolean('aplicada_en_nomina')->default(false);
            $table->string('nomina_periodo')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamo_cuotas');
    }
};
