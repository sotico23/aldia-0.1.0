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
        Schema::table('conductores', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('estado');
            $table->decimal('lng', 11, 7)->nullable()->after('lat');
            $table->timestamp('ultima_actualizacion')->nullable()->after('lng');
        });
    }

    public function down(): void
    {
        Schema::table('conductores', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'ultima_actualizacion']);
        });
    }
};
