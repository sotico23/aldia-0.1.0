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
        Schema::create('tesoreria', function (Blueprint $table) {
            $table->id();
            $table->string('tipo')->default('ingreso');
            $table->string('cuenta')->nullable();
            $table->decimal('monto', 12, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->date('fecha')->nullable();
            $table->string('referencia')->nullable();
            $table->string('cuenta_bancaria_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tesoreria');
    }
};
