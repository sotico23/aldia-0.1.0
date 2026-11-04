<?php

namespace App\Console\Commands;

use App\Models\ChannelCredential;
use App\Models\SystemIntegration;
use App\Services\ChannelConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RegisterChannelWebhooks extends Command
{
    protected $signature = 'channels:register-webhooks
        {--dry-run : Muestra las URLs y acciones sin persistir ni llamar APIs externas}
        {--owner= : Limita el registro de canales personalizados a un owner_id}';

    protected $description = 'Registra los webhooks globales y personalizados de Telegram y WhatsApp.';

    private bool $failed = false;

    public function handle(ChannelConfigService $channelConfig): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ownerId = $this->option('owner') !== null ? (int) $this->option('owner') : null;

        if ($dryRun) {
            $this->warn('Dry-run: no se guardarán secretos ni se harán llamadas externas.');
        }

        if ($ownerId === null) {
            $this->registerGlobalTelegram($channelConfig, $dryRun);
            $this->registerGlobalWhatsApp($channelConfig, $dryRun);
        }

        $credentials = ChannelCredential::withoutGlobalScopes()
            ->where(function ($query) {
                $query->where('telegram_mode', ChannelConfigService::MODE_CUSTOM)
                    ->orWhere('whatsapp_mode', ChannelConfigService::MODE_CUSTOM);
            })
            ->when($ownerId !== null, fn ($query) => $query->where('owner_id', $ownerId))
            ->orderBy('owner_id')
            ->get();

        foreach ($credentials as $credential) {
            if ($credential->telegram_mode === ChannelConfigService::MODE_CUSTOM) {
                $this->registerTenantTelegram($credential, $channelConfig, $dryRun);
            }

            if ($credential->whatsapp_mode === ChannelConfigService::MODE_CUSTOM) {
                $this->registerTenantWhatsApp($credential, $channelConfig, $dryRun);
            }
        }

        return $this->failed ? self::FAILURE : self::SUCCESS;
    }

    private function registerGlobalTelegram(ChannelConfigService $channelConfig, bool $dryRun): void
    {
        $integration = SystemIntegration::forProvider(ChannelConfigService::CHANNEL_TELEGRAM)->first();
        $config = $channelConfig->globalTelegram();
        $token = $config['bot_token'];

        if (! filled($token)) {
            $this->warn('Telegram global: omitido (sin token configurado).');

            return;
        }

        $integration ??= new SystemIntegration(['provider' => ChannelConfigService::CHANNEL_TELEGRAM]);
        $secret = $integration->telegram_webhook_secret ?: Str::random(48);
        $url = route('webhooks.telegram.global');

        if ($dryRun) {
            $this->line("Telegram global: registraría {$url}.");

            return;
        }

        $integration->telegram_bot_token ??= $token;
        $integration->telegram_webhook_secret = $secret;
        $integration->is_active = true;
        $integration->save();

        $this->setTelegramWebhook($token, $secret, $url, 'Telegram global');
    }

    private function registerTenantTelegram(
        ChannelCredential $credential,
        ChannelConfigService $channelConfig,
        bool $dryRun
    ): void {
        if (! filled($credential->telegram_bot_token)) {
            $this->warn("Telegram tenant {$credential->owner_id}: omitido (sin token configurado).");

            return;
        }

        $secret = $credential->telegram_webhook_secret ?: Str::random(48);
        $key = $channelConfig->ensureWebhookKey($credential, 'telegram_webhook_key');
        $url = route('webhooks.telegram.tenant', ['key' => $key]);

        if ($dryRun) {
            $this->line("Telegram tenant {$credential->owner_id}: registraría el webhook personalizado.");

            return;
        }

        $credential->telegram_webhook_secret = $secret;
        $credential->save();

        $this->setTelegramWebhook(
            $credential->telegram_bot_token,
            $secret,
            $url,
            "Telegram tenant {$credential->owner_id}"
        );
    }

    private function registerGlobalWhatsApp(ChannelConfigService $channelConfig, bool $dryRun): void
    {
        $config = $channelConfig->globalWhatsApp();

        if (! filled($config['access_token']) || ! filled($config['phone_number_id'])) {
            $this->warn('WhatsApp global: omitido (faltan credenciales o Phone Number ID).');

            return;
        }

        $integration = SystemIntegration::firstOrNew(['provider' => ChannelConfigService::CHANNEL_WHATSAPP]);
        $integration->whatsapp_webhook_key ??= Str::random(48);
        $integration->whatsapp_verify_token ??= Str::random(48);
        $targetId = $config['business_id'] ?: $config['phone_number_id'];
        $url = route('webhooks.whatsapp.global');

        if (! filled($config['app_secret'] ?? null)) {
            $this->warn("WhatsApp global: configura App Secret y callback {$url} antes de habilitar los eventos.");

            return;
        }

        if ($dryRun) {
            $this->line("WhatsApp global: registraría la suscripción del recurso {$targetId}; callback {$url}.");

            return;
        }

        $integration->whatsapp_phone_number_id ??= $config['phone_number_id'];
        $integration->whatsapp_access_token ??= $config['access_token'];
        $integration->whatsapp_business_id ??= $config['business_id'];
        $integration->whatsapp_api_version ??= $config['api_version'] ?: 'v22.0';
        $integration->is_active = true;
        $integration->save();

        $this->line("WhatsApp global callback: {$url}");
        $this->subscribeWhatsApp($config, $targetId, 'WhatsApp global');
    }

    private function registerTenantWhatsApp(
        ChannelCredential $credential,
        ChannelConfigService $channelConfig,
        bool $dryRun
    ): void {
        if (! filled($credential->whatsapp_access_token) || ! filled($credential->whatsapp_phone_number_id)) {
            $this->warn("WhatsApp tenant {$credential->owner_id}: omitido (faltan credenciales).");

            return;
        }

        $key = $channelConfig->ensureWebhookKey($credential, 'whatsapp_webhook_key');
        $credential->whatsapp_verify_token ??= Str::random(48);
        $config = [
            'access_token' => $credential->whatsapp_access_token,
            'phone_number_id' => $credential->whatsapp_phone_number_id,
            'business_id' => $credential->whatsapp_business_id,
            'api_version' => $credential->whatsapp_api_version ?: 'v22.0',
        ];
        $targetId = $config['business_id'] ?: $config['phone_number_id'];
        $url = route('webhooks.whatsapp.tenant', ['key' => $key]);

        if (! filled($credential->whatsapp_app_secret)) {
            $this->warn("WhatsApp tenant {$credential->owner_id}: configura App Secret y callback personalizado antes de habilitar los eventos.");

            return;
        }

        if ($dryRun) {
            $this->line("WhatsApp tenant {$credential->owner_id}: registraría la suscripción del recurso {$targetId}; callback personalizado.");

            return;
        }

        $credential->save();
        $this->line("WhatsApp tenant {$credential->owner_id} callback: {$url}");
        $this->subscribeWhatsApp($config, $targetId, "WhatsApp tenant {$credential->owner_id}");
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function subscribeWhatsApp(array $config, string $targetId, string $label): void
    {
        $version = $config['api_version'] ?: 'v22.0';

        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->withToken($config['access_token'])
                ->post("https://graph.facebook.com/{$version}/{$targetId}/subscribed_apps");
            $body = $response->json();

            if ($response->successful() && ($body['success'] ?? false)) {
                $this->info("{$label}: suscripción completada. Configura la URL callback en el panel de Meta.");

                return;
            }

            $this->failed = true;
            $this->error("{$label}: Meta rechazó la suscripción (HTTP {$response->status()}).");
        } catch (\Throwable $exception) {
            $this->failed = true;
            Log::error('Channel webhook registration failed for WhatsApp', [
                'channel' => $label,
                'error' => $exception->getMessage(),
            ]);
            $this->error("{$label}: no se pudo completar la suscripción.");
        }
    }

    private function setTelegramWebhook(string $token, string $secret, string $url, string $label): void
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => config('services.http_verify_tls')])
                ->post("https://api.telegram.org/bot{$token}/setWebhook", [
                    'url' => $url,
                    'secret_token' => $secret,
                    'drop_pending_updates' => true,
                ]);
            $body = $response->json();

            if ($response->successful() && ($body['ok'] ?? false)) {
                $this->info("{$label}: webhook registrado en {$url}.");

                return;
            }

            $this->failed = true;
            $this->error("{$label}: Telegram rechazó el registro (HTTP {$response->status()}).");
        } catch (\Throwable $exception) {
            $this->failed = true;
            Log::error('Channel webhook registration failed for Telegram', [
                'channel' => $label,
                'error' => $exception->getMessage(),
            ]);
            $this->error("{$label}: no se pudo registrar el webhook.");
        }
    }
}
