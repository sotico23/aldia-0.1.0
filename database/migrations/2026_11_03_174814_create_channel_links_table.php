<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('bot_scope', 40);
            $table->string('external_id', 80);
            $table->foreignId('linked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'bot_scope', 'external_id'], 'channel_links_unique');
            $table->index(['owner_id', 'channel', 'bot_scope'], 'channel_links_owner_idx');
        });

        DB::table('channel_credentials')
            ->whereNotNull('telegram_chat_id')
            ->orderBy('id')
            ->chunkById(200, function ($credentials): void {
                foreach ($credentials as $credential) {
                    DB::table('channel_links')->insertOrIgnore([
                        'owner_id' => $credential->owner_id,
                        'channel' => 'telegram',
                        'bot_scope' => $credential->bot_type === 'global'
                            ? 'global'
                            : 'tenant:'.$credential->owner_id,
                        'external_id' => (string) $credential->telegram_chat_id,
                        'metadata' => null,
                        'linked_at' => $credential->telegram_linked_at,
                        'revoked_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_links');
    }
};
