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
        Schema::create('reclutamiento', function (Blueprint $table) {
            $table->id();
            $table->string('candidato');
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('puesto')->nullable();
            $table->date('fecha_postulacion')->nullable();
            $table->string('estado')->default('nuevo');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reclutamiento');
    }
};
