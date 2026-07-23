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
        Schema::table('mensajes', function (Blueprint $table) {
            $table->string('archivo_path')->nullable()->after('contenido');
            $table->string('archivo_nombre')->nullable()->after('archivo_path');
            $table->string('archivo_tipo')->nullable()->after('archivo_nombre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            //
        });
    }
};
