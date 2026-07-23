<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carga_diaria_renovacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('renovacion_id')->constrained('carga_diaria_renovaciones')->onDelete('cascade');
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->integer('cantidad_bordo')->default(0);
            $table->integer('cantidad_llena')->default(0);
            $table->integer('cantidad_vacia')->default(0);
            $table->integer('cantidad_faltante')->default(0);
            $table->integer('cantidad_vendida')->default(0);
            $table->integer('cantidad_devuelta')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carga_diaria_renovacion_detalles');
    }
};
