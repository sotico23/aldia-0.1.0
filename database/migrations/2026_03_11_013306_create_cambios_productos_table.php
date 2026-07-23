<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cambios_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained()->onDelete('cascade');
            $table->foreignId('producto_entregado_id')->constrained('productos')->onDelete('cascade');
            $table->foreignId('producto_recibido_id')->constrained('productos')->onDelete('cascade');
            $table->integer('cantidad')->default(1);
            $table->decimal('diferencia_precio', 12, 2)->default(0);
            $table->text('motivo')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_productos');
    }
};
