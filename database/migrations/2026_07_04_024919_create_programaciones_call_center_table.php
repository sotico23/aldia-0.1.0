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
        Schema::create('programaciones_call_center', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('contacto_type')->nullable();
            $table->unsignedBigInteger('contacto_id')->nullable();
            $table->string('numero_telefono')->nullable();
            $table->dateTime('fecha_programada');
            $table->integer('recordatorio_minutos')->default(5);
            $table->dateTime('notificado_at')->nullable();
            $table->boolean('completada')->default(false);
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['fecha_programada', 'notificado_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programaciones_call_center');
    }
};
