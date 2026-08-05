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
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_access_token')->nullable();
            $table->string('whatsapp_business_id')->nullable();
            $table->string('whatsapp_api_version')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_phone_number_id', 'whatsapp_access_token', 'whatsapp_business_id', 'whatsapp_api_version']);
        });
    }
};
