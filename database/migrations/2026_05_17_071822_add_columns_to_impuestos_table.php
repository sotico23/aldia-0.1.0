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
        Schema::table('impuestos', function (Blueprint $table) {
            $table->string('descripcion')->nullable()->after('codigo');
            $table->date('fecha_inicio')->nullable()->after('descripcion');
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            $table->renameColumn('activo', 'estado');
            $table->string('estado')->default('activo')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('impuestos', function (Blueprint $table) {
            //
        });
    }
};
