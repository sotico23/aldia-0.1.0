<?php

namespace App\Services;

use App\Models\ChannelCredential;
use App\Models\ChannelLink;
use App\Models\SystemIntegration;
use App\Models\WebSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ChannelConfigService
{
    public const CHANNEL_TELEGRAM = 'telegram';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const MODE_GLOBAL = 'global';

    public const MODE_CUSTOM = 'custom';

    public const SCOPE_GLOBAL = 'global';

    /**
     * @return array{bot_token: ?string, bot_username: ?string, webhook_secret: ?string, is_active: bool, source: string}
     */
    public function globalTelegram(): array
    {
        $integration = SystemIntegration::forProvider(self::CHANNEL_TELEGRAM)->first();
        $legacy = WebSetting::getSettings();

        $botToken = $integration?->telegram_bot_token ?: $legacy->global_telegram_bot_token;
        $botUsername = $integration?->telegram_bot_username ?: $legacy->global_telegram_bot_username;

        return [
            'bot_token' => $botToken,
            'bot_username' => $botUsername,
            'webhook_secret' => $integration?->telegram_webhook_secret,
            'is_active' => (bool) ($integration?->is_active ?? filled($botToken)),
            'source' => self::MODE_GLOBAL,
        ];
    }

    /**
     * @return array{phone_number_id: ?string, access_token: ?string, business_id: ?string, api_version: string, verify_token: ?string, app_secret: ?string, webhook_key: ?string, is_active: bool, source: string}
     */
    public function globalWhatsApp(): array
    {
        $integration = SystemIntegration::forProvider(self::CHANNEL_WHATSAPP)->first();
        $legacy = WebSetting::getSettings();

        $accessToken = $integration?->whatsapp_access_token ?: $legacy->whatsapp_access_token;

        return [
            'phone_number_id' => $integration?->whatsapp_phone_number_id ?: $legacy->whatsapp_phone_number_id,
            'access_token' => $accessToken,
            'business_id' => $integration?->whatsapp_business_id ?: $legacy->whatsapp_business_id,
            'api_version' => $integration?->whatsapp_api_version ?: ($legacy->whatsapp_api_version ?: 'v22.0'),
            'verify_token' => $integration?->whatsapp_verify_token,
            'app_secret' => $integration?->whatsapp_app_secret,
            'webhook_key' => $integration?->whatsapp_webhook_key,
            'is_active' => (bool) ($integration?->is_active ?? filled($accessToken)),
            'source' => self::MODE_GLOBAL,
        ];
    }

    /**
     * @return array{owner_id: int, mode: string, bot_token: ?string, bot_username: ?string, bot_id: ?string, webhook_secret: ?string, webhook_key: ?string, is_active: bool, source: string}
     */
    public function tenantTelegram(int $ownerId): array
    {
        $credential = $this->tenantCredential($ownerId);

        if (! $this->isCustomTelegram($credential)) {
            return [
                ...$this->globalTelegram(),
                'owner_id' => $ownerId,
                'mode' => self::MODE_GLOBAL,
                'bot_id' => null,
                'webhook_key' => null,
            ];
        }

        return [
            'owner_id' => $ownerId,
            'mode' => self::MODE_CUSTOM,
            'bot_token' => $credential->telegram_bot_token,
            'bot_username' => $credential->telegram_bot_username,
            'bot_id' => $credential->telegram_bot_id,
            'webhook_secret' => $credential->telegram_webhook_secret,
            'webhook_key' => $credential->telegram_webhook_key,
            'is_active' => filled($credential->telegram_bot_token),
            'source' => self::MODE_CUSTOM,
        ];
    }

    /**
     * @return array{owner_id: int, mode: string, phone_number_id: ?string, access_token: ?string, business_id: ?string, api_version: string, wa_id: ?string, verify_token: ?string, app_secret: ?string, webhook_key: ?string, is_active: bool, source: string}
     */
    public function tenantWhatsApp(int $ownerId): array
    {
        $credential = $this->tenantCredential($ownerId);

        if (! $this->isCustomWhatsApp($credential)) {
            return [
                ...$this->globalWhatsApp(),
                'owner_id' => $ownerId,
                'mode' => self::MODE_GLOBAL,
                'wa_id' => null,
                'webhook_key' => null,
            ];
        }

        return [
            'owner_id' => $ownerId,
            'mode' => self::MODE_CUSTOM,
            'phone_number_id' => $credential->whatsapp_phone_number_id,
            'access_token' => $credential->whatsapp_access_token,
            'business_id' => $credential->whatsapp_business_id,
            'api_version' => $credential->whatsapp_api_version ?: 'v22.0',
            'wa_id' => $credential->whatsapp_wa_id,
            'verify_token' => $credential->whatsapp_verify_token,
            'app_secret' => $credential->whatsapp_app_secret,
            'webhook_key' => $credential->whatsapp_webhook_key,
            'is_active' => filled($credential->whatsapp_access_token)
                && filled($credential->whatsapp_phone_number_id),
            'source' => self::MODE_CUSTOM,
        ];
    }

    public function tenantCredential(int $ownerId): ?ChannelCredential
    {
        return ChannelCredential::withoutGlobalScopes()
            ->where('owner_id', $ownerId)
            ->first();
    }

    public function isCustomTelegram(?ChannelCredential $credential): bool
    {
        return $credential !== null
            && $credential->telegram_mode === self::MODE_CUSTOM
            && filled($credential->telegram_bot_token);
    }

    public function isCustomWhatsApp(?ChannelCredential $credential): bool
    {
        return $credential !== null
            && $credential->whatsapp_mode === self::MODE_CUSTOM
            && filled($credential->whatsapp_access_token);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function telegramConfigByWebhookKey(?string $webhookKey): ?array
    {
        if (! $webhookKey || $webhookKey === self::SCOPE_GLOBAL) {
            return $this->globalTelegram();
        }

        $credential = ChannelCredential::withoutGlobalScopes()
            ->where('telegram_webhook_key', $webhookKey)
            ->first();

        if (! $credential) {
            return null;
        }

        return $this->tenantTelegram((int) $credential->owner_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function whatsAppConfigByWebhookKey(?string $webhookKey): ?array
    {
        if (! $webhookKey || $webhookKey === self::SCOPE_GLOBAL) {
            return $this->globalWhatsApp();
        }

        $credential = ChannelCredential::withoutGlobalScopes()
            ->where('whatsapp_webhook_key', $webhookKey)
            ->first();

        if (! $credential) {
            return null;
        }

        return $this->tenantWhatsApp((int) $credential->owner_id);
    }

    public function botScopeForOwner(int $ownerId): string
    {
        return 'tenant:'.$ownerId;
    }

    public function globalBotScope(): string
    {
        return self::SCOPE_GLOBAL;
    }

    public function resolveBotScope(?ChannelCredential $credential): string
    {
        if ($credential === null || $credential->telegram_mode !== self::MODE_CUSTOM) {
            return self::SCOPE_GLOBAL;
        }

        return $this->botScopeForOwner((int) $credential->owner_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function activeTelegramLink(string $botScope, string $externalId): ?array
    {
        $link = ChannelLink::withoutGlobalScopes()
            ->where('channel', self::CHANNEL_TELEGRAM)
            ->where('bot_scope', $botScope)
            ->where('external_id', $externalId)
            ->active()
            ->first();

        if (! $link) {
            return null;
        }

        return [
            'owner_id' => $link->owner_id,
            'linked_by_user_id' => $link->linked_by_user_id,
            'metadata' => $link->metadata,
        ];
    }

    public function ensureWebhookKey(Model $model, string $column): string
    {
        $current = $model->getAttribute($column);

        if (filled($current)) {
            return $current;
        }

        do {
            $key = Str::random(48);
        } while ($model->newQuery()->where($column, $key)->exists());

        $model->setAttribute($column, $key);

        return $key;
    }
}
