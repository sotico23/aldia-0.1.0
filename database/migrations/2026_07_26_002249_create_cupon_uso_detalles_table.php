<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupon_uso_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupon_uso_id')->constrained('cupon_usos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained()->cascadeOnDelete();
            $table->integer('cantidad')->default(1);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->string('descuento_tipo')->nullable();
            $table->decimal('descuento_valor', 12, 2)->nullable();
            $table->decimal('monto_descuento', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupon_uso_detalle');
    }
};
