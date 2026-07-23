<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carga_diaria_renovaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('carga_diaria_id')->constrained('carga_diarias')->onDelete('cascade');
            $table->date('fecha');
            $table->string('tipo')->default('recarga'); // recarga, cierre
            $table->text('notas')->nullable();
            $table->integer('total_productos_llenos')->default(0);
            $table->integer('total_productos_vacios')->default(0);
            $table->integer('total_productos_faltantes')->default(0);
            $table->decimal('ventas_totales', 10, 2)->default(0);
            $table->decimal('devoluciones_totales', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carga_diaria_renovaciones');
    }
};
