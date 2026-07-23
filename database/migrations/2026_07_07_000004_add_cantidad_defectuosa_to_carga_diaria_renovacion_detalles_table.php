<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carga_diaria_renovacion_detalles', function (Blueprint $table) {
            $table->integer('cantidad_defectuosa')->default(0)->after('cantidad_faltante');
        });
    }

    public function down(): void
    {
        Schema::table('carga_diaria_renovacion_detalles', function (Blueprint $table) {
            $table->dropColumn('cantidad_defectuosa');
        });
    }
};
