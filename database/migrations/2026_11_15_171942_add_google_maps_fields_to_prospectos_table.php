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
        Schema::table('prospectos', function (Blueprint $table) {
            $table->string('google_maps_key', 64)->nullable();
            $table->text('google_maps_url')->nullable();
            $table->text('sitio_web')->nullable();
            $table->unique(['owner_id', 'google_maps_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospectos', function (Blueprint $table) {
            $table->dropUnique(['owner_id', 'google_maps_key']);
            $table->dropColumn(['google_maps_key', 'google_maps_url', 'sitio_web']);
        });
    }
};
