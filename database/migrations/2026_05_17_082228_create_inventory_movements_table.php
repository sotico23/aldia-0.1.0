<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('product_id')->constrained('productos')->onDelete('cascade');
            $table->foreignId('source_warehouse_id')->nullable()->constrained('almacenes')->onDelete('set null');
            $table->foreignId('destination_warehouse_id')->nullable()->constrained('almacenes')->onDelete('set null');
            $table->integer('quantity')->comment('Puede ser negativo para operaciones de gas');
            $table->enum('type', ['INGRESO', 'EGRESO', 'TRASLADO']);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('owner_id');
            $table->index(['product_id', 'created_at']);
            $table->index(['source_warehouse_id', 'created_at']);
            $table->index(['destination_warehouse_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
