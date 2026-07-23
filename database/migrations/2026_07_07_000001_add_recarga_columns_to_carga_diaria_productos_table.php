<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carga_diaria_productos', function (Blueprint $table) {
            $table->integer('cantidad_llena')->default(0)->after('cantidad_devuelta');
            $table->integer('cantidad_vacia')->default(0)->after('cantidad_llena');
            $table->integer('cantidad_faltante')->default(0)->after('cantidad_vacia');
        });
    }

    public function down(): void
    {
        Schema::table('carga_diaria_productos', function (Blueprint $table) {
            $table->dropColumn(['cantidad_llena', 'cantidad_vacia', 'cantidad_faltante']);
        });
    }
};
