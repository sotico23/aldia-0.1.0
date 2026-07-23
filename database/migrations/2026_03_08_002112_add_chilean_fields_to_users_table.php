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
        Schema::table('users', function (Blueprint $table) {
            $table->string('rut')->nullable()->unique()->after('email');
            $table->string('telefono')->nullable()->after('rut');
            $table->text('direccion')->nullable()->after('telefono');
            $table->string('ciudad')->nullable()->after('direccion');
            $table->string('region')->nullable()->after('ciudad');
            $table->string('comuna')->nullable()->after('region');
            $table->date('fecha_nacimiento')->nullable()->after('comuna');
            $table->string('genero')->nullable()->after('fecha_nacimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
