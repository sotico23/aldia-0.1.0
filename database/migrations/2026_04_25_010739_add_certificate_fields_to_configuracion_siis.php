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
            $table->string('certificado_path')->nullable()->after('telefono');
            $table->string('certificado_password')->nullable()->after('certificado_path');
            $table->date('certificado_vencimiento')->nullable()->after('certificado_password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configuracion_siis', function (Blueprint $table) {
            //
        });
    }
};
