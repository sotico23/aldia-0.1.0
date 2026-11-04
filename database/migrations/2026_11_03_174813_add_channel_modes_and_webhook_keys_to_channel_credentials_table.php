<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_credentials', function (Blueprint $table) {
            $table->string('telegram_mode', 10)->default('global')->after('bot_type');
            $table->string('telegram_bot_id', 50)->nullable()->after('telegram_bot_username');
            $table->text('telegram_webhook_secret')->nullable()->after('telegram_bot_id');
            $table->string('telegram_webhook_key', 64)->nullable()->unique()->after('telegram_webhook_secret');

            $table->string('whatsapp_mode', 10)->default('global')->after('whatsapp_api_version');
            $table->string('whatsapp_wa_id', 50)->nullable()->after('whatsapp_business_id');
            $table->timestamp('whatsapp_linked_at')->nullable()->after('whatsapp_wa_id');
            $table->text('whatsapp_verify_token')->nullable()->after('whatsapp_linked_at');
            $table->text('whatsapp_app_secret')->nullable()->after('whatsapp_verify_token');
            $table->string('whatsapp_webhook_key', 64)->nullable()->unique()->after('whatsapp_app_secret');

            $table->index(['telegram_chat_id', 'telegram_mode'], 'cc_telegram_link_idx');
            $table->index(['whatsapp_phone_number_id', 'whatsapp_mode'], 'cc_whatsapp_link_idx');
        });
    }

    public function down(): void
    {
        Schema::table('channel_credentials', function (Blueprint $table) {
            $table->dropIndex('cc_telegram_link_idx');
            $table->dropIndex('cc_whatsapp_link_idx');
            $table->dropUnique(['telegram_webhook_key']);
            $table->dropUnique(['whatsapp_webhook_key']);
            $table->dropColumn([
                'telegram_mode',
                'telegram_bot_id',
                'telegram_webhook_secret',
                'telegram_webhook_key',
                'whatsapp_mode',
                'whatsapp_wa_id',
                'whatsapp_linked_at',
                'whatsapp_verify_token',
                'whatsapp_app_secret',
                'whatsapp_webhook_key',
            ]);
        });
    }
};
