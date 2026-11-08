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
            $table->string('theme_preset', 50)->nullable()->default('emerald')->after('app_author');
            $table->string('primary_color', 20)->nullable()->default('#059669')->after('theme_preset');
            $table->string('secondary_color', 20)->nullable()->default('#10B981')->after('primary_color');
            $table->string('accent_color', 20)->nullable()->default('#34D399')->after('secondary_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn([
                'theme_preset',
                'primary_color',
                'secondary_color',
                'accent_color',
            ]);
        });
    }
};
