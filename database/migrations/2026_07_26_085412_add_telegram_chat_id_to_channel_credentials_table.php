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
            $table->string('telegram_chat_id')->nullable()->after('telegram_bot_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channel_credentials', function (Blueprint $table) {
            $table->dropColumn('telegram_chat_id');
        });
    }
};
