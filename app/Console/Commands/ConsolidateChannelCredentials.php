<?php

namespace App\Console\Commands;

use App\Models\ChannelCredential;
use App\Models\ChannelLink;
use App\Models\SystemIntegration;
use App\Services\ChannelConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConsolidateChannelCredentials extends Command
{
    protected $signature = 'channels:consolidate-credentials
        {--dry-run : Muestra los cambios sin escribir en la base de datos}
        {--owner= : Limita la consolidación a un owner_id}';

    protected $description = 'Consolida la configuración global y por tenant de Telegram/WhatsApp en las tablas canónicas.';

    /**
     * @var array<string, int>
     */
    protected array $stats = [
        'global_telegram_updated' => 0,
        'global_whatsapp_updated' => 0,
        'credentials_updated' => 0,
        'telegram_links_created' => 0,
        'whatsapp_links_created' => 0,
        'linking_tokens_updated' => 0,
    ];

    public function handle(ChannelConfigService $channelConfig): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ownerId = $this->option('owner') !== null ? (int) $this->option('owner') : null;

        $this->info($dryRun
            ? 'Ejecutando en modo dry-run. No se escribirán cambios.'
            : 'Consolidando configuración de canales...');

        $this->consolidateGlobalChannels($dryRun);
        $this->consolidateTenantCredentials($dryRun, $ownerId, $channelConfig);
        $this->consolidateLinkingTokens($dryRun);

        $this->newLine();
        $this->table(
            ['Cambio', 'Cantidad'],
            collect($this->stats)
                ->map(fn (int $count, string $key): array => [$key, $count])
                ->values()
                ->all()
        );

        if ($dryRun) {
            $this->warn('Dry-run finalizado. Ejecuta sin --dry-run para aplicar los cambios.');
        } else {
            $this->info('Consolidación finalizada.');
        }

        return self::SUCCESS;
    }

    protected function consolidateGlobalChannels(bool $dryRun): void
    {
        $legacy = DB::table('web_settings')->orderByDesc('id')->first();

        $telegram = SystemIntegration::firstOrNew(['provider' => 'telegram']);

        if (! $telegram->telegram_bot_token && $legacy?->global_telegram_bot_token) {
            $telegram->telegram_bot_token = $legacy->global_telegram_bot_token;
        }

        if (! $telegram->telegram_bot_username && $legacy?->global_telegram_bot_username) {
            $telegram->telegram_bot_username = $legacy->global_telegram_bot_username;
        }

        if (filled($telegram->telegram_bot_token) && ! $telegram->is_active) {
            $telegram->is_active = true;
        }

        $shouldSaveTelegram = $telegram->exists
            ? $telegram->isDirty()
            : (filled($telegram->telegram_bot_token) || filled($telegram->telegram_bot_username));

        if ($shouldSaveTelegram) {
            $this->stats['global_telegram_updated']++;

            if (! $dryRun) {
                $telegram->save();
            }
        }

        $whatsapp = SystemIntegration::firstOrNew(['provider' => 'whatsapp']);

        if (! $whatsapp->whatsapp_phone_number_id && $legacy?->whatsapp_phone_number_id) {
            $whatsapp->whatsapp_phone_number_id = $legacy->whatsapp_phone_number_id;
        }

        if (! $whatsapp->whatsapp_access_token && $legacy?->whatsapp_access_token) {
            $whatsapp->whatsapp_access_token = $legacy->whatsapp_access_token;
        }

        if (! $whatsapp->whatsapp_business_id && $legacy?->whatsapp_business_id) {
            $whatsapp->whatsapp_business_id = $legacy->whatsapp_business_id;
        }

        if (! $whatsapp->whatsapp_api_version) {
            $whatsapp->whatsapp_api_version = $legacy?->whatsapp_api_version ?: 'v22.0';
        }

        $n8n = SystemIntegration::forProvider('n8n')->first();

        if ($n8n) {
            $whatsapp->whatsapp_phone_number_id = $whatsapp->whatsapp_phone_number_id
                ?: $n8n->whatsapp_phone_number_id;
            $whatsapp->whatsapp_access_token = $whatsapp->whatsapp_access_token
                ?: $n8n->whatsapp_access_token;
            $whatsapp->whatsapp_business_id = $whatsapp->whatsapp_business_id
                ?: $n8n->whatsapp_business_id;
            $whatsapp->whatsapp_api_version = $whatsapp->whatsapp_api_version
                ?: ($n8n->whatsapp_api_version ?: 'v22.0');
        }

        if (filled($whatsapp->whatsapp_access_token) && ! $whatsapp->is_active) {
            $whatsapp->is_active = true;
        }

        $shouldSaveWhatsapp = $whatsapp->exists
            ? $whatsapp->isDirty()
            : (filled($whatsapp->whatsapp_access_token)
                || filled($whatsapp->whatsapp_phone_number_id)
                || filled($whatsapp->whatsapp_business_id));

        if ($shouldSaveWhatsapp) {
            $this->stats['global_whatsapp_updated']++;

            if (! $dryRun) {
                $whatsapp->save();
            }
        }
    }

    protected function consolidateTenantCredentials(
        bool $dryRun,
        ?int $ownerId,
        ChannelConfigService $channelConfig
    ): void {
        ChannelCredential::withoutGlobalScopes()
            ->when($ownerId !== null, fn ($query) => $query->where('owner_id', $ownerId))
            ->orderBy('id')
            ->chunkById(200, function ($credentials) use ($dryRun, $channelConfig): void {
                foreach ($credentials as $credential) {
                    $this->consolidateTenantCredential($credential, $dryRun, $channelConfig);
                }
            });
    }

    protected function consolidateTenantCredential(
        ChannelCredential $credential,
        bool $dryRun,
        ChannelConfigService $channelConfig
    ): void {
        $telegramMode = $credential->telegram_mode ?: ChannelConfigService::MODE_GLOBAL;

        if (
            $telegramMode === ChannelConfigService::MODE_GLOBAL
            && filled($credential->telegram_bot_token)
            && $credential->bot_type !== ChannelConfigService::MODE_GLOBAL
        ) {
            $telegramMode = ChannelConfigService::MODE_CUSTOM;
        }

        $whatsappMode = $credential->whatsapp_mode ?: ChannelConfigService::MODE_GLOBAL;

        if (
            $whatsappMode === ChannelConfigService::MODE_GLOBAL
            && filled($credential->whatsapp_access_token)
            && filled($credential->whatsapp_phone_number_id)
        ) {
            $whatsappMode = ChannelConfigService::MODE_CUSTOM;
        }

        $credential->telegram_mode = $telegramMode;
        $credential->whatsapp_mode = $whatsappMode;

        if ($telegramMode === ChannelConfigService::MODE_CUSTOM) {
            $channelConfig->ensureWebhookKey($credential, 'telegram_webhook_key');
        }

        if ($whatsappMode === ChannelConfigService::MODE_CUSTOM) {
            $channelConfig->ensureWebhookKey($credential, 'whatsapp_webhook_key');
        }

        if ($credential->isDirty()) {
            $this->stats['credentials_updated']++;

            if (! $dryRun) {
                $credential->save();
            }
        }

        $telegramScope = $telegramMode === ChannelConfigService::MODE_CUSTOM
            ? $channelConfig->botScopeForOwner((int) $credential->owner_id)
            : $channelConfig->globalBotScope();

        if (filled($credential->telegram_chat_id)) {
            $this->stats['telegram_links_created'] += $this->createLink(
                ownerId: (int) $credential->owner_id,
                channel: ChannelConfigService::CHANNEL_TELEGRAM,
                botScope: $telegramScope,
                externalId: (string) $credential->telegram_chat_id,
                linkedAt: $credential->telegram_linked_at,
                dryRun: $dryRun,
            );
        }

        if (filled($credential->whatsapp_wa_id)) {
            $whatsappScope = $whatsappMode === ChannelConfigService::MODE_CUSTOM
                ? $channelConfig->botScopeForOwner((int) $credential->owner_id)
                : $channelConfig->globalBotScope();

            $this->stats['whatsapp_links_created'] += $this->createLink(
                ownerId: (int) $credential->owner_id,
                channel: ChannelConfigService::CHANNEL_WHATSAPP,
                botScope: $whatsappScope,
                externalId: (string) $credential->whatsapp_wa_id,
                linkedAt: $credential->whatsapp_linked_at,
                dryRun: $dryRun,
            );
        }
    }

    protected function createLink(
        int $ownerId,
        string $channel,
        string $botScope,
        string $externalId,
        mixed $linkedAt,
        bool $dryRun
    ): int {
        $exists = ChannelLink::withoutGlobalScopes()
            ->where('owner_id', $ownerId)
            ->where('channel', $channel)
            ->where('bot_scope', $botScope)
            ->where('external_id', $externalId)
            ->exists();

        if ($exists) {
            return 0;
        }

        if (! $dryRun) {
            ChannelLink::create([
                'owner_id' => $ownerId,
                'channel' => $channel,
                'bot_scope' => $botScope,
                'external_id' => $externalId,
                'linked_at' => $linkedAt,
            ]);
        }

        return 1;
    }

    protected function consolidateLinkingTokens(bool $dryRun): void
    {
        DB::table('telegram_linking_tokens')
            ->whereNull('bot_scope')
            ->orderBy('id')
            ->chunkById(200, function ($tokens) use ($dryRun): void {
                foreach ($tokens as $token) {
                    $scope = $token->bot_type === ChannelConfigService::MODE_GLOBAL
                        ? ChannelConfigService::SCOPE_GLOBAL
                        : 'tenant:'.$token->owner_id;

                    $this->stats['linking_tokens_updated']++;

                    if (! $dryRun) {
                        DB::table('telegram_linking_tokens')
                            ->where('id', $token->id)
                            ->update(['bot_scope' => $scope]);
                    }
                }
            });
    }
}
