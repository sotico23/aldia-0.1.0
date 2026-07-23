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
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropColumn('envase_retornado');
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->integer('cantidad_retornada')->nullable()->after('subtotal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->dropColumn('cantidad_retornada');
        });

        Schema::table('detalle_ventas', function (Blueprint $table) {
            $table->boolean('envase_retornado')->default(true)->after('subtotal');
        });
    }
};
