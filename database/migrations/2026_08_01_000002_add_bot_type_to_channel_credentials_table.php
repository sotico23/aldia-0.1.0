<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_credentials', function (Blueprint $table) {
            $table->string('bot_type')->nullable()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('channel_credentials', function (Blueprint $table) {
            $table->dropColumn('bot_type');
        });
    }
};
