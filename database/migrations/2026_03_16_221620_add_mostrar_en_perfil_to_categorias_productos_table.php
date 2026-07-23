<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->boolean('mostrar_en_perfil')->default(true)->after('imagen');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('mostrar_en_perfil')->default(true)->after('video');
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function (Blueprint $table) {
            $table->dropColumn('mostrar_en_perfil');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('mostrar_en_perfil');
        });
    }
};
