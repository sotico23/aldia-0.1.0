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
        Schema::create('prospectos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('empresa')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('fuente')->nullable();
            $table->string('estado')->default('nuevo'); // nuevo, contactado, calificado, perdido, convertido
            $table->decimal('valor_estimado', 12, 2)->default(0);
            $table->date('fecha_seguimiento')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospectos');
    }
};
