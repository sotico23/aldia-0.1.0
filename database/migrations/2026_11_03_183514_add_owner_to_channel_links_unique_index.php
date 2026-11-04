<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_links', function (Blueprint $table) {
            $table->dropUnique('channel_links_unique');
            $table->unique(
                ['owner_id', 'channel', 'bot_scope', 'external_id'],
                'channel_links_owner_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('channel_links', function (Blueprint $table) {
            $table->dropUnique('channel_links_owner_unique');
            $table->unique(['channel', 'bot_scope', 'external_id'], 'channel_links_unique');
        });
    }
};
