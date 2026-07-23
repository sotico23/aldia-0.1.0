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
        Schema::create('grupo_trabajo_conductores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_trabajo_id')->constrained('grupo_trabajos')->cascadeOnDelete();
            $table->foreignId('conductor_id')->constrained('conductores')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupo_trabajo_conductores');
    }
};
