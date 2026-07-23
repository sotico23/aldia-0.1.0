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
        Schema::table('productos', function (Blueprint $table) {
            $table->string('imagen')->nullable()->after('stock_minimo');
            $table->string('imagen2')->nullable()->after('imagen');
            $table->string('imagen3')->nullable()->after('imagen2');
            $table->string('imagen4')->nullable()->after('imagen3');
            $table->string('imagen5')->nullable()->after('imagen4');
            $table->string('video')->nullable()->after('imagen5');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['imagen', 'imagen2', 'imagen3', 'imagen4', 'imagen5', 'video']);
        });
    }
};
