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
        Schema::table('web_settings', function (Blueprint $table) {
            $table->boolean('nav_quienes_somos_visible')->default(true);
            $table->boolean('nav_feedback_visible')->default(true);
            $table->boolean('nav_fundacion_visible')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn(['nav_quienes_somos_visible', 'nav_feedback_visible', 'nav_fundacion_visible']);
        });
    }
};
