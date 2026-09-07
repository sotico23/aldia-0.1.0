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
        Schema::create('delivery_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('repartidor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('zona_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->foreignId('asignador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo', 40)->default('auto_asignado');
            $table->timestamps();

            $table->index('owner_id');
            $table->index('pedido_id');
            $table->index('repartidor_id');
            $table->index('zona_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_asignaciones');
    }
};
