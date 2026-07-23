<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carga_diaria_renovaciones', function (Blueprint $table) {
            $table->integer('total_productos_defectuosos')->default(0)->after('total_productos_faltantes');
        });
    }

    public function down(): void
    {
        Schema::table('carga_diaria_renovaciones', function (Blueprint $table) {
            $table->dropColumn('total_productos_defectuosos');
        });
    }
};
