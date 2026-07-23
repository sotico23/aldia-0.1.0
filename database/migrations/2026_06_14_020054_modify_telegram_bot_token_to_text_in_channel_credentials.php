<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_credentials', function (Blueprint $table) {
            $table->text('telegram_bot_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('channel_credentials', function (Blueprint $table) {
            $table->string('telegram_bot_token', 255)->nullable()->change();
        });
    }
};
