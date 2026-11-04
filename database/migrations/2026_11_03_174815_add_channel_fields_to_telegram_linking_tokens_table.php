<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_linking_tokens', function (Blueprint $table) {
            $table->string('channel', 20)->default('telegram')->after('token');
            $table->string('bot_scope', 40)->nullable()->after('channel');
            $table->string('external_id', 80)->nullable()->after('telegram_chat_id');
            $table->json('metadata')->nullable()->after('external_id');

            $table->index(['channel', 'token'], 'linking_channel_token_idx');
        });

        DB::table('telegram_linking_tokens')
            ->orderBy('id')
            ->chunkById(200, function ($tokens): void {
                foreach ($tokens as $token) {
                    DB::table('telegram_linking_tokens')
                        ->where('id', $token->id)
                        ->update([
                            'bot_scope' => $token->bot_type === 'global'
                                ? 'global'
                                : 'tenant:'.$token->owner_id,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('telegram_linking_tokens', function (Blueprint $table) {
            $table->dropIndex('linking_channel_token_idx');
            $table->dropColumn(['channel', 'bot_scope', 'external_id', 'metadata']);
        });
    }
};
