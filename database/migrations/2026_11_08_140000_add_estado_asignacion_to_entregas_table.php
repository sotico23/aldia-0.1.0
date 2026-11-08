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
        Schema::table('entregas', function (Blueprint $table) {
            $table->string('estado_asignacion', 20)
                ->default('asignado')
                ->comment('asignado = repartidor/conductor fijo, libre = bolsa de ofertas')
                ->after('repartidor_id');
            $table->index('estado_asignacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $table) {
            $table->dropIndex(['estado_asignacion']);
            $table->dropColumn(['estado_asignacion']);
        });
    }
};
