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
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->string('proyecto')->nullable();
            $table->string('empleado')->nullable();
            $table->date('fecha')->nullable();
            $table->decimal('horas', 5, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->string('estado')->default('aprobado');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
