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
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->decimal('lat', 10, 8)->nullable()->after('color');
            $table->decimal('lng', 11, 8)->nullable()->after('lat');
            $table->integer('velocidad')->default(0)->after('lng');
            $table->timestamp('ultima_actualizacion')->nullable()->after('velocidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehiculos', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'velocidad', 'ultima_actualizacion']);
        });
    }
};
