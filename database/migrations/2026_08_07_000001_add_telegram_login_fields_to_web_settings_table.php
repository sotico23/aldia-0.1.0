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
            $table->string('telegram_login_bot_name')->nullable()->after('facebook_redirect_uri');
            $table->text('telegram_login_bot_token')->nullable()->after('telegram_login_bot_name');
            $table->string('telegram_login_redirect_uri')->nullable()->after('telegram_login_bot_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn(['telegram_login_bot_name', 'telegram_login_bot_token', 'telegram_login_redirect_uri']);
        });
    }
};
