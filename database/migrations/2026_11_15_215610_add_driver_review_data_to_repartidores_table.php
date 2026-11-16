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
        Schema::table('repartidores', function (Blueprint $table) {
            $table->string('vehiculo_tipo', 30)->nullable()->after('driver_type');
            $table->timestamp('antecedentes_aceptados_at')->nullable()->after('rejected_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('repartidores', function (Blueprint $table) {
            $table->dropColumn(['vehiculo_tipo', 'antecedentes_aceptados_at']);
        });
    }
};
