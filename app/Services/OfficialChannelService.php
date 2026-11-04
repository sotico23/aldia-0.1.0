<?php

namespace App\Services;

use App\Models\SystemIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OfficialChannelService
{
    public const MASKED_SECRET = '••••••••••••••••';

    public function __construct(protected ChannelConfigService $channelConfig) {}

    /**
     * @return array<string, mixed>
     */
    public function telegramPayload(): array
    {
        $config = $this->channelConfig->globalTelegram();

        return [
            'bot_username' => $config['bot_username'] ?? '',
            'bot_token' => filled($config['bot_token']) ? self::MASKED_SECRET : '',
            'has_bot_token' => filled($config['bot_token']),
            'has_webhook_secret' => filled($config['webhook_secret']),
            'is_active' => (bool) ($config['is_active'] ?? false),
            'webhook_url' => $this->telegramWebhookUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function whatsAppPayload(): array
    {
        $config = $this->channelConfig->globalWhatsApp();

        return [
            'phone_number_id' => $config['phone_number_id'] ?? '',
            'business_id' => $config['business_id'] ?? '',
            'api_version' => $config['api_version'] ?? 'v22.0',
            'access_token' => filled($config['access_token']) ? self::MASKED_SECRET : '',
            'has_access_token' => filled($config['access_token']),
            'verify_token' => $config['verify_token'] ?? '',
            'has_verify_token' => filled($config['verify_token']),
            'app_secret' => filled($config['app_secret']) ? self::MASKED_SECRET : '',
            'has_app_secret' => filled($config['app_secret']),
            'webhook_key' => $config['webhook_key'] ?? '',
            'is_active' => (bool) ($config['is_active'] ?? false),
            'webhook_url' => $this->whatsAppWebhookUrl(),
        ];
    }

    /**
     * @param  array{bot_token?: ?string, bot_username?: ?string, is_active?: bool}  $data
     */
    public function updateTelegram(array $data): SystemIntegration
    {
        $integration = SystemIntegration::firstOrNew(['provider' => ChannelConfigService::CHANNEL_TELEGRAM]);

        if (filled($data['bot_token'] ?? null) && $data['bot_token'] !== self::MASKED_SECRET) {
            $integration->telegram_bot_token = $data['bot_token'];
        }

        if (array_key_exists('bot_username', $data)) {
            $integration->telegram_bot_username = filled($data['bot_username'])
                ? ltrim((string) $data['bot_username'], '@')
                : null;
        }

        if (! $integration->telegram_bot_token) {
            throw ValidationException::withMessages([
                'bot_token' => 'Debes configurar el token del bot oficial de Telegram.',
            ]);
        }

        $integration->is_active = (bool) ($data['is_active'] ?? true);

        if (! $integration->telegram_webhook_secret) {
            $integration->telegram_webhook_secret = Str::random(48);
        }

        $integration->save();

        return $integration;
    }

    /**
     * @param  array{phone_number_id?: ?string, access_token?: ?string, business_id?: ?string, api_version?: ?string, verify_token?: ?string, app_secret?: ?string, is_active?: bool}  $data
     */
    public function updateWhatsApp(array $data): SystemIntegration
    {
        $integration = SystemIntegration::firstOrNew(['provider' => ChannelConfigService::CHANNEL_WHATSAPP]);

        if (array_key_exists('phone_number_id', $data)) {
            $integration->whatsapp_phone_number_id = filled($data['phone_number_id'])
                ? $data['phone_number_id']
                : null;
        }

        if (
            filled($data['access_token'] ?? null)
            && $data['access_token'] !== self::MASKED_SECRET
        ) {
            $integration->whatsapp_access_token = $data['access_token'];
        }

        if (array_key_exists('business_id', $data)) {
            $integration->whatsapp_business_id = filled($data['business_id'])
                ? $data['business_id']
                : null;
        }

        if (filled($data['api_version'] ?? null)) {
            $integration->whatsapp_api_version = $data['api_version'];
        }

        if (! $integration->whatsapp_api_version) {
            $integration->whatsapp_api_version = 'v22.0';
        }

        if (filled($data['verify_token'] ?? null)) {
            $integration->whatsapp_verify_token = $data['verify_token'];
        }

        if (! $integration->whatsapp_verify_token) {
            $integration->whatsapp_verify_token = Str::random(48);
        }

        if (
            filled($data['app_secret'] ?? null)
            && $data['app_secret'] !== self::MASKED_SECRET
        ) {
            $integration->whatsapp_app_secret = $data['app_secret'];
        }

        if (! $integration->whatsapp_webhook_key) {
            $integration->whatsapp_webhook_key = Str::random(48);
        }

        $integration->is_active = (bool) ($data['is_active'] ?? true);

        if ($integration->is_active && (! $integration->whatsapp_access_token || ! $integration->whatsapp_phone_number_id)) {
            throw ValidationException::withMessages([
                'access_token' => 'Para activar WhatsApp oficial debes configurar el Access Token y el Phone Number ID.',
            ]);
        }

        $integration->save();

        return $integration;
    }

    /**
     * @return array{success: bool, message: string, bot_username?: string, bot_id?: int, bot_name?: string}
     */
    public function testTelegram(?string $token = null, ?string $expectedUsername = null): array
    {
        $botToken = $token ?: $this->channelConfig->globalTelegram()['bot_token'];

        if (! $botToken) {
            return [
                'success' => false,
                'message' => 'No hay un token de bot de Telegram configurado.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => config('services.http_verify_tls')])
                ->get("https://api.telegram.org/bot{$botToken}/getMe");

            $body = $response->json();

            if (! $response->successful() || ! ($body['ok'] ?? false) || ! ($body['result']['is_bot'] ?? false)) {
                return [
                    'success' => false,
                    'message' => 'El Token de Telegram ingresado no es válido o expiró.',
                ];
            }

            $botInfo = $body['result'];
            $username = $botInfo['username'] ?? '';

            if ($expectedUsername && ltrim($expectedUsername, '@') !== $username) {
                return [
                    'success' => false,
                    'message' => 'El username del bot no coincide con el token proporcionado.',
                ];
            }

            return [
                'success' => true,
                'message' => 'Conexión con Telegram exitosa. El bot está activo.',
                'bot_username' => $username,
                'bot_id' => $botInfo['id'] ?? null,
                'bot_name' => $botInfo['first_name'] ?? '',
            ];
        } catch (ConnectionException $e) {
            Log::error('Official Telegram test connection exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'No se pudo conectar con la API de Telegram. Verifica tu conexión e inténtalo nuevamente.',
            ];
        } catch (\Exception $e) {
            Log::error('Official Telegram test exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'El Token de Telegram ingresado no es válido o expiró.',
            ];
        }
    }

    /**
     * @return array{success: bool, message: string, business_id?: string, phone_number?: string, name?: string}
     */
    public function testWhatsApp(?string $accessToken = null, ?string $phoneNumberId = null): array
    {
        $config = $this->channelConfig->globalWhatsApp();
        $token = $accessToken ?: $config['access_token'];
        $phoneId = $phoneNumberId ?: $config['phone_number_id'];
        $version = $config['api_version'] ?: 'v22.0';

        if (! $token || ! $phoneId) {
            return [
                'success' => false,
                'message' => 'No hay Access Token o Phone Number ID de WhatsApp configurado.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withToken($token)
                ->get("https://graph.facebook.com/{$version}/{$phoneId}");

            $body = $response->json();

            if ($response->successful() && isset($body['id'])) {
                return [
                    'success' => true,
                    'message' => 'Conexión exitosa con WhatsApp Cloud API.',
                    'business_id' => $body['business']['id'] ?? $body['id'],
                    'phone_number' => $body['display_phone'] ?? '',
                    'name' => $body['name'] ?? '',
                ];
            }

            $error = $body['error']['message'] ?? 'Credenciales inválidas';

            return [
                'success' => false,
                'message' => "Error: {$error}",
            ];
        } catch (ConnectionException $e) {
            Log::error('Official WhatsApp test connection exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'No se pudo conectar con la API de WhatsApp. Verifica tu conexión e inténtalo nuevamente.',
            ];
        } catch (\Exception $e) {
            Log::error('Official WhatsApp test exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'No se pudo conectar con la API de WhatsApp.',
            ];
        }
    }

    /**
     * @return array{success: bool, message: string, webhook_url?: string, webhook_configured?: bool}
     */
    public function setTelegramWebhook(): array
    {
        $integration = SystemIntegration::forProvider(ChannelConfigService::CHANNEL_TELEGRAM)->first();

        if (! $integration?->telegram_bot_token) {
            throw ValidationException::withMessages([
                'bot_token' => 'No hay un token de bot de Telegram configurado. Guarda las credenciales primero.',
            ]);
        }

        if (! $integration->telegram_webhook_secret) {
            $integration->telegram_webhook_secret = Str::random(48);
            $integration->save();
        }

        $webhookUrl = $this->telegramWebhookUrl();

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => config('services.http_verify_tls')])
                ->post("https://api.telegram.org/bot{$integration->telegram_bot_token}/setWebhook", [
                    'url' => $webhookUrl,
                    'secret_token' => $integration->telegram_webhook_secret,
                    'drop_pending_updates' => true,
                ]);

            $body = $response->json();

            if ($response->successful() && ($body['ok'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Webhook registrado exitosamente con Telegram.',
                    'webhook_url' => $webhookUrl,
                    'webhook_configured' => true,
                ];
            }

            return [
                'success' => false,
                'message' => $body['description'] ?? 'Telegram rechazó la configuración del webhook.',
            ];
        } catch (ConnectionException $e) {
            Log::error('Official Telegram setWebhook connection exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'No se pudo conectar con la API de Telegram. Verifica tu conexión e inténtalo nuevamente.',
            ];
        } catch (\Exception $e) {
            Log::error('Official Telegram setWebhook exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error al registrar el webhook de Telegram.',
            ];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function deleteTelegramWebhook(): array
    {
        $integration = SystemIntegration::forProvider(ChannelConfigService::CHANNEL_TELEGRAM)->first();

        if (! $integration?->telegram_bot_token) {
            throw ValidationException::withMessages([
                'bot_token' => 'No hay un token de bot de Telegram configurado.',
            ]);
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => config('services.http_verify_tls')])
                ->post("https://api.telegram.org/bot{$integration->telegram_bot_token}/deleteWebhook", [
                    'drop_pending_updates' => true,
                ]);

            $body = $response->json();

            if ($response->successful() && ($body['ok'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Webhook de Telegram eliminado correctamente.',
                ];
            }

            return [
                'success' => false,
                'message' => $body['description'] ?? 'Telegram rechazó la eliminación del webhook.',
            ];
        } catch (\Exception $e) {
            Log::error('Official Telegram deleteWebhook exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error al eliminar el webhook de Telegram.',
            ];
        }
    }

    /**
     * Meta no permite cambiar la Callback URL por API. Este método suscribe la
     * app al número/WABA y devuelve los datos que el administrador debe copiar
     * manualmente en el panel de Meta.
     *
     * @return array{success: bool, message: string, webhook_url?: string, verify_token?: string, manual_setup?: bool}
     */
    public function setWhatsAppWebhook(): array
    {
        $integration = SystemIntegration::forProvider(ChannelConfigService::CHANNEL_WHATSAPP)->first();

        if (! $integration?->whatsapp_access_token) {
            throw ValidationException::withMessages([
                'access_token' => 'No hay un Access Token de WhatsApp configurado. Guarda las credenciales primero.',
            ]);
        }

        $targetId = $integration->whatsapp_business_id ?: $integration->whatsapp_phone_number_id;

        if (! $targetId) {
            throw ValidationException::withMessages([
                'business_id' => 'No hay un Business ID o Phone Number ID de WhatsApp configurado.',
            ]);
        }

        if (! $integration->whatsapp_verify_token) {
            $integration->whatsapp_verify_token = Str::random(48);
            $integration->save();
        }

        $version = $integration->whatsapp_api_version ?: 'v22.0';

        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->withToken($integration->whatsapp_access_token)
                ->post("https://graph.facebook.com/{$version}/{$targetId}/subscribed_apps");

            $body = $response->json();

            if ($response->successful() && ($body['success'] ?? false)) {
                return [
                    'success' => true,
                    'message' => 'Webhook registrado exitosamente con WhatsApp.',
                    'webhook_url' => $this->whatsAppWebhookUrl(),
                    'verify_token' => $integration->whatsapp_verify_token,
                    'manual_setup' => true,
                ];
            }

            $error = $body['error']['message'] ?? 'La API de WhatsApp rechazó la suscripción del webhook.';

            return [
                'success' => false,
                'message' => "Error: {$error}",
            ];
        } catch (ConnectionException $e) {
            Log::error('Official WhatsApp setWebhook connection exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'No se pudo conectar con la API de WhatsApp. Verifica tu conexión e inténtalo nuevamente.',
            ];
        } catch (\Exception $e) {
            Log::error('Official WhatsApp setWebhook exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Error al registrar el webhook de WhatsApp.',
            ];
        }
    }

    public function telegramWebhookUrl(): string
    {
        return Route::has('webhooks.telegram.global')
            ? route('webhooks.telegram.global')
            : route('webhooks.telegram');
    }

    public function whatsAppWebhookUrl(): string
    {
        return Route::has('webhooks.whatsapp.global')
            ? route('webhooks.whatsapp.global')
            : route('webhooks.whatsapp');
    }
}
