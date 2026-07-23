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
        Schema::create('hitos', function (Blueprint $table) {
            $table->id();
            $table->string('proyecto')->nullable();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->integer('progreso')->default(0);
            $table->string('estado')->default('pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hitos');
    }
};
