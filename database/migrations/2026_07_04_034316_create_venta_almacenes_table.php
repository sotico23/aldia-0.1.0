<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_almacenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained()->onDelete('cascade');
            $table->foreignId('almacen_id')->constrained('almacenes')->onDelete('cascade');
            $table->decimal('cantidad_descontada', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['venta_id', 'almacen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_almacenes');
    }
};
