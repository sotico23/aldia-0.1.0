<?php

namespace App\Services;

use App\Models\ApiIntegration;
use App\Models\ChannelCredential;
use App\Models\PaymentConfig;
use App\Models\SystemIntegration;
use App\Models\User;
use App\Scopes\OwnerScope;
use Illuminate\Support\Facades\Auth;

class TenantCredentialsService
{
    public const MASKED = '••••••••••••••••';

    /**
     * Fields that must be masked when exposing credentials to the frontend.
     *
     * @var array<string, list<string>>
     */
    protected const SECRET_FIELDS = [
        'webpay' => ['api_key'],
        'mercadopago' => ['mercadopago_access_token'],
        'paypal' => ['paypal_client_secret'],
        'n8n' => ['api_key'],
        'telegram' => ['telegram_bot_token'],
        'whatsapp' => ['whatsapp_access_token'],
        'facebook_meta' => ['access_token'],
        'shopify' => ['access_token'],
        'woocommerce' => ['consumer_secret'],
        'mercado_libre' => ['access_token'],
    ];

    /**
     * Resolve the effective tenant (owner) id for a user.
     */
    public function resolveOwnerId(?int $userId = null): int
    {
        $user = $userId !== null
            ? User::withoutGlobalScope(OwnerScope::class)->find($userId)
            : Auth::user();

        if (! $user) {
            throw new \InvalidArgumentException('No se pudo resolver el propietario del tenant.');
        }

        return $user->getOwnerId();
    }

    /**
     * Get decrypted credentials for a provider.
     *
     * Priority: tenant ApiIntegration record, then legacy storage
     * (PaymentConfig / ChannelCredential / SystemIntegration).
     *
     * @return array<string, mixed>|null
     */
    public function get(string $provider, ?int $userId = null): ?array
    {
        $ownerId = $this->resolveOwnerId($userId);

        $integration = $this->find($ownerId, $provider);

        if (! empty($integration?->credentials)) {
            return $integration->credentials;
        }

        return $this->fromLegacy($provider, $ownerId);
    }

    /**
     * Persist credentials for a provider, preserving existing secret values
     * when the incoming payload is empty or masked. Keeps legacy storage in
     * sync (dual-write) so existing consumers keep working.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function save(int $ownerId, string $provider, array $credentials, ?string $environment = null, bool $isActive = false): ApiIntegration
    {
        $current = $this->find($ownerId, $provider)?->credentials
            ?: $this->fromLegacy($provider, $ownerId)
            ?: [];

        $merged = $current;

        foreach ($credentials as $key => $value) {
            if ($value !== null && $value !== '' && $value !== self::MASKED) {
                $merged[$key] = $value;
            } elseif (! array_key_exists($key, $merged)) {
                $merged[$key] = null;
            }
        }

        $integration = ApiIntegration::withoutGlobalScope(OwnerScope::class)
            ->updateOrCreate(
                ['owner_id' => $ownerId, 'provider' => $provider],
                [
                    'credentials' => $merged,
                    'environment' => $environment,
                    'is_active' => $isActive,
                ]
            );

        $this->syncLegacy($provider, $ownerId, $merged, $environment, $isActive);

        return $integration;
    }

    /**
     * Mask the secret fields of a provider's credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    public function mask(string $provider, array $credentials): array
    {
        $secrets = self::SECRET_FIELDS[$provider] ?? [];

        foreach ($secrets as $field) {
            if (isset($credentials[$field]) && $credentials[$field] !== null && $credentials[$field] !== '') {
                $credentials[$field] = self::MASKED;
            }
        }

        return $credentials;
    }

    private function find(int $ownerId, string $provider): ?ApiIntegration
    {
        return ApiIntegration::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->where('provider', $provider)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromLegacy(string $provider, int $ownerId): ?array
    {
        return match ($provider) {
            'webpay', 'mercadopago', 'paypal' => $this->fromPaymentConfig($provider, $ownerId),
            'telegram', 'whatsapp' => $this->fromChannelCredential($provider, $ownerId),
            'n8n' => $this->fromN8n($ownerId),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromPaymentConfig(string $provider, int $ownerId): ?array
    {
        $config = PaymentConfig::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->first();

        if (! $config) {
            return null;
        }

        return match ($provider) {
            'webpay' => [
                'commerce_code' => $config->commerce_code,
                'api_key' => $config->api_key,
                'environment' => $config->environment ?? 'integration',
                'is_active' => (bool) $config->is_active,
            ],
            'paypal' => [
                'paypal_client_id' => $config->paypal_client_id,
                'paypal_client_secret' => $config->paypal_client_secret,
                'paypal_mode' => $config->paypal_mode ?? 'sandbox',
                'is_active' => (bool) $config->paypal_active,
            ],
            default => [
                'mercadopago_public_key' => $config->mercadopago_public_key,
                'mercadopago_access_token' => $config->mercadopago_access_token,
                'mercadopago_mode' => $config->mercadopago_mode ?? 'sandbox',
                'is_active' => (bool) $config->mercadopago_active,
            ],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromChannelCredential(string $provider, int $ownerId): ?array
    {
        $credentials = ChannelCredential::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->first();

        if (! $credentials) {
            return null;
        }

        return match ($provider) {
            'telegram' => [
                'telegram_bot_token' => $credentials->telegram_bot_token,
                'telegram_bot_username' => $credentials->telegram_bot_username,
                'telegram_chat_id' => $credentials->telegram_chat_id,
                'is_active' => (bool) $credentials->telegram_bot_token,
            ],
            default => [
                'whatsapp_phone_number_id' => $credentials->whatsapp_phone_number_id,
                'whatsapp_access_token' => $credentials->whatsapp_access_token,
                'whatsapp_business_id' => $credentials->whatsapp_business_id,
                'whatsapp_api_version' => $credentials->whatsapp_api_version,
                'is_active' => (bool) $credentials->whatsapp_access_token,
            ],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromN8n(int $ownerId): ?array
    {
        $tenant = ChannelCredential::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->first();

        $system = SystemIntegration::forProvider('n8n')->first();

        if (! $tenant?->n8n_api_key && ! $system?->telegram_proxy_url && ! $system?->api_key) {
            return null;
        }

        return [
            'base_url' => $system?->base_url,
            'webhook_url' => $system?->webhook_url,
            'telegram_proxy_url' => $tenant?->n8n_telegram_proxy_webhook_url ?: $system?->telegram_proxy_url,
            'api_key' => $tenant?->n8n_api_key ?: $system?->api_key,
            'is_active' => (bool) ($tenant?->n8n_api_key || $system?->is_active),
        ];
    }

    /**
     * Dual-write payment providers back to PaymentConfig so checkout flows
     * (WebpayService, PayPalController, MercadoPagoController) keep working.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function syncLegacy(string $provider, int $ownerId, array $credentials, ?string $environment, bool $isActive): void
    {
        if (! in_array($provider, ['webpay', 'paypal', 'mercadopago'], true)) {
            return;
        }

        $config = PaymentConfig::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $ownerId)
            ->first();

        $environment ??= $credentials['environment'] ?? $this->defaultEnvironment($provider);

        $data = match ($provider) {
            'webpay' => [
                'commerce_code' => $credentials['commerce_code'] ?? null,
                'api_key' => $credentials['api_key'] ?? null,
                'environment' => $environment,
                'is_active' => $isActive,
            ],
            'paypal' => [
                'paypal_client_id' => $credentials['paypal_client_id'] ?? null,
                'paypal_client_secret' => $credentials['paypal_client_secret'] ?? null,
                'paypal_mode' => $environment,
                'paypal_active' => $isActive,
            ],
            default => [
                'mercadopago_public_key' => $credentials['mercadopago_public_key'] ?? null,
                'mercadopago_access_token' => $credentials['mercadopago_access_token'] ?? null,
                'mercadopago_mode' => $environment,
                'mercadopago_active' => $isActive,
            ],
        };

        if ($config) {
            $config->update($data);
        } else {
            PaymentConfig::withoutGlobalScope(OwnerScope::class)
                ->create(['owner_id' => $ownerId] + $data);
        }
    }

    private function defaultEnvironment(string $provider): string
    {
        return match ($provider) {
            'webpay' => 'integration',
            'paypal' => 'sandbox',
            default => 'sandbox',
        };
    }
}
