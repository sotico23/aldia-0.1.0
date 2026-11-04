<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('channel_credentials')
            ->where(function ($query): void {
                $query->whereNotNull('telegram_chat_id')
                    ->orWhereNotNull('whatsapp_wa_id');
            })
            ->orderBy('id')
            ->chunkById(200, function ($credentials): void {
                foreach ($credentials as $credential) {
                    $ownerId = (int) $credential->owner_id;
                    $telegramMode = $credential->telegram_mode ?: 'global';
                    $whatsappMode = $credential->whatsapp_mode ?: 'global';

                    if (
                        $telegramMode === 'global'
                        && filled($credential->telegram_bot_token)
                        && $credential->bot_type !== 'global'
                    ) {
                        $telegramMode = 'custom';
                    }

                    if (
                        $whatsappMode === 'global'
                        && filled($credential->whatsapp_access_token)
                        && filled($credential->whatsapp_phone_number_id)
                    ) {
                        $whatsappMode = 'custom';
                    }

                    if (filled($credential->telegram_chat_id)) {
                        DB::table('channel_links')->insertOrIgnore([
                            'owner_id' => $ownerId,
                            'channel' => 'telegram',
                            'bot_scope' => $telegramMode === 'custom' ? 'tenant:'.$ownerId : 'global',
                            'external_id' => (string) $credential->telegram_chat_id,
                            'linked_at' => $credential->telegram_linked_at,
                            'revoked_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    if (filled($credential->whatsapp_wa_id)) {
                        DB::table('channel_links')->insertOrIgnore([
                            'owner_id' => $ownerId,
                            'channel' => 'whatsapp',
                            'bot_scope' => $whatsappMode === 'custom' ? 'tenant:'.$ownerId : 'global',
                            'external_id' => (string) $credential->whatsapp_wa_id,
                            'linked_at' => $credential->whatsapp_linked_at,
                            'revoked_at' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    public function down(): void {}
};
