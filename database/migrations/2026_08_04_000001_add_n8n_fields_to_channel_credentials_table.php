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
        Schema::table('channel_credentials', function (Blueprint $table) {
            $table->string('n8n_telegram_proxy_webhook_url')->nullable();
            $table->text('n8n_api_key')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_credentials', function (Blueprint $table) {
            $table->dropColumn(['n8n_telegram_proxy_webhook_url', 'n8n_api_key']);
        });
    }
};
