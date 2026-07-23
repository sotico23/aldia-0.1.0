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
        Schema::create('asientos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('numero', 20)->unique();
            $table->string('descripcion');
            $table->string('tipo')->default('diario'); // diario, apertura, cierre
            $table->decimal('total_debe', 12, 2)->default(0);
            $table->decimal('total_haber', 12, 2)->default(0);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asientos');
    }
};
