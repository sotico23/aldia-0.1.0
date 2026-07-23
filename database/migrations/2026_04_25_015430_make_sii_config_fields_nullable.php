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
        Schema::table('configuracion_siis', function (Blueprint $table) {
            $table->string('rut', 15)->nullable()->change();
            $table->string('razon_social', 150)->nullable()->change();
            $table->string('giro', 150)->nullable()->change();
            $table->unsignedInteger('acteco')->nullable()->change();
            $table->string('direccion', 150)->nullable()->change();
            $table->string('comuna', 60)->nullable()->change();
            $table->string('ciudad', 60)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracion_siis', function (Blueprint $table) {
            $table->string('rut', 15)->nullable(false)->change();
            $table->string('razon_social', 150)->nullable(false)->change();
            $table->string('giro', 150)->nullable(false)->change();
            $table->unsignedInteger('acteco')->nullable(false)->change();
            $table->string('direccion', 150)->nullable(false)->change();
            $table->string('comuna', 60)->nullable(false)->change();
            $table->string('ciudad', 60)->nullable(false)->change();
        });
    }
};
