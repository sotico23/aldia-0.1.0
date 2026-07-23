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
            $table->string('nav_quienes_somos_image')->nullable();
            $table->string('nav_quienes_somos_subtitle')->nullable();

            $table->string('nav_feedback_image')->nullable();
            $table->string('nav_feedback_subtitle')->nullable();

            $table->string('nav_fundacion_image')->nullable();
            $table->string('nav_fundacion_subtitle')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn([
                'nav_quienes_somos_image',
                'nav_quienes_somos_subtitle',
                'nav_feedback_image',
                'nav_feedback_subtitle',
                'nav_fundacion_image',
                'nav_fundacion_subtitle',
            ]);
        });
    }
};
