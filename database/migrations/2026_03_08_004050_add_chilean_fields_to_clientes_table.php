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
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('rut')->nullable()->unique()->after('nit');
            $table->string('ciudad')->nullable()->after('direccion');
            $table->string('region')->nullable()->after('ciudad');
            $table->string('comuna')->nullable()->after('region');
            $table->string('giro')->nullable()->after('comuna');
            $table->string('contacto')->nullable()->after('giro');
            $table->string('telefono_contacto')->nullable()->after('contacto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            //
        });
    }
};
