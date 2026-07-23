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
        Schema::create('cupon_producto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupon_id')->constrained('cupones')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->enum('descuento_tipo', ['porcentaje', 'precio_fijo'])->default('porcentaje');
            $table->decimal('descuento_valor', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['cupon_id', 'producto_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cupon_producto');
    }
};
