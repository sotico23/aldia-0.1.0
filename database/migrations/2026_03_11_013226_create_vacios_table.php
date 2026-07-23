<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained()->onDelete('cascade');
            $table->integer('cantidad')->default(0);
            $table->integer('cantidad_minima')->default(5);
            $table->enum('estado', ['disponible', 'entregado', 'retornado', 'perdido'])->default('disponible');
            $table->string('ubicacion')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacios');
    }
};
