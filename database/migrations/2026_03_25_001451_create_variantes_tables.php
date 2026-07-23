<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('tipo');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('variante_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('variantes')->cascadeOnDelete();
            $table->string('valor');
            $table->string('codigo')->nullable();
            $table->timestamps();
        });

        Schema::create('producto_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('variantes')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('sku_variantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->decimal('precio_compra', 12, 2)->nullable();
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('stock_minimo', 12, 3)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('sku_variante_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sku_variante_id')->constrained('sku_variantes')->cascadeOnDelete();
            $table->foreignId('variante_valor_id')->constrained('variante_valores')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_variante_valores');
        Schema::dropIfExists('sku_variantes');
        Schema::dropIfExists('producto_variantes');
        Schema::dropIfExists('variante_valores');
        Schema::dropIfExists('variantes');
    }
};
