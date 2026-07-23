<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupo_trabajo_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grupo_trabajo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->decimal('meta_monto', 18, 2)->default(0);
            $table->integer('meta_cantidad')->default(0);
            $table->decimal('meta_kg', 12, 2)->default(0);
            $table->decimal('meta_l', 12, 2)->default(0);
            $table->enum('estado', ['activa', 'completada', 'cancelada'])->default('activa');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'grupo_trabajo_id']);
            $table->index(['fecha_inicio', 'fecha_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_trabajo_asignaciones');
    }
};
