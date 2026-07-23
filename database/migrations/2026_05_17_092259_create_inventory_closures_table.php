<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->index();
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('almacen_id')->nullable()->constrained('almacenes')->onDelete('set null');
            $table->date('closure_date');
            $table->enum('type', ['BODEGA', 'GENERAL'])->default('BODEGA');
            $table->enum('status', ['ABIERTO', 'CERRADO', 'AUDITADO'])->default('ABIERTO');
            $table->decimal('total_products', 12, 0)->default(0);
            $table->decimal('total_stock', 12, 3)->default(0);
            $table->decimal('expected_stock', 12, 3)->default(0);
            $table->decimal('difference', 12, 3)->default(0);
            $table->text('observations')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_closures');
    }
};
