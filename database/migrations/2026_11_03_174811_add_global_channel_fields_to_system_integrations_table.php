<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_integrations', function (Blueprint $table) {
            $table->text('telegram_bot_token')->nullable()->after('api_key');
            $table->string('telegram_bot_username')->nullable()->after('telegram_bot_token');
            $table->text('telegram_webhook_secret')->nullable()->after('telegram_bot_username');

            $table->text('whatsapp_verify_token')->nullable()->after('whatsapp_api_version');
            $table->text('whatsapp_app_secret')->nullable()->after('whatsapp_verify_token');
            $table->string('whatsapp_webhook_key', 64)->nullable()->unique()->after('whatsapp_app_secret');

            $table->text('signing_secret')->nullable()->after('api_key');
        });

        Schema::table('system_integrations', function (Blueprint $table) {
            $table->text('whatsapp_access_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('system_integrations', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_webhook_key']);
            $table->dropColumn([
                'telegram_bot_token',
                'telegram_bot_username',
                'telegram_webhook_secret',
                'whatsapp_verify_token',
                'whatsapp_app_secret',
                'whatsapp_webhook_key',
                'signing_secret',
            ]);
        });

        Schema::table('system_integrations', function (Blueprint $table) {
            $table->string('whatsapp_access_token')->nullable()->change();
        });
    }
};
