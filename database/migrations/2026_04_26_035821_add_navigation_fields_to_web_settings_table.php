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
            $table->string('nav_quienes_somos_label')->nullable()->default('Quiénes Somos');
            $table->longText('nav_quienes_somos_content')->nullable();

            $table->string('nav_feedback_label')->nullable()->default('Feedback');
            $table->longText('nav_feedback_content')->nullable();

            $table->string('nav_fundacion_label')->nullable()->default('Nuestra Fundación');
            $table->longText('nav_fundacion_content')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            //
        });
    }
};
