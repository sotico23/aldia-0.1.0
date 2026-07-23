<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->time('hora_entrada')->nullable()->after('sueldo_liquido_pactado');
            $table->time('hora_salida')->nullable()->after('hora_entrada');
        });
    }

    public function down(): void
    {
        Schema::table('empleados', function (Blueprint $table) {
            $table->dropColumn(['hora_entrada', 'hora_salida']);
        });
    }
};
