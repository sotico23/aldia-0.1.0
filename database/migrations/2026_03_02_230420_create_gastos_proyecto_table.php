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
        Schema::create('gastos_proyecto', function (Blueprint $table) {
            $table->id();
            $table->string('proyecto')->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('monto', 12, 2)->default(0);
            $table->date('fecha')->nullable();
            $table->string('categoria')->nullable();
            $table->string('estado')->default('pendiente');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos_proyecto');
    }
};
