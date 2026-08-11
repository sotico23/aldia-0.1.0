<?php

namespace App\Console\Commands;

use App\Models\ChannelCredential;
use App\Models\WebSetting;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:set-webhook
                            {--bot=global : Bot to configure (global or custom)}
                            {--owner= : Owner id for a custom bot}
                            {--webhook= : Custom webhook URL (defaults to {app.url}/webhooks/telegram)}
                            {--info : Show getWebhookInfo instead of setting it}';

    protected $description = 'Configure the Telegram bot webhook so /start deep links reach Laravel and close the channel linking cycle';

    public function handle(): int
    {
        $webhookUrl = $this->option('webhook') ?: route('webhooks.telegram');
        $isInfo = (bool) $this->option('info');

        if ($this->option('bot') === 'custom') {
            $ownerId = $this->option('owner');
            if (! $ownerId) {
                $this->error('--owner is required when using --bot=custom');

                return self::INVALID;
            }

            $credential = ChannelCredential::where('owner_id', $ownerId)->first();

            if (! $credential || ! $credential->telegram_bot_token) {
                $this->error("No se encontró configuración de Telegram para el owner {$ownerId}.");

                return self::INVALID;
            }

            $botToken = $credential->telegram_bot_token;
        } else {
            $settings = WebSetting::getSettings();
            $botToken = $settings->global_telegram_bot_token ?? config('services.telegram.bot_token');

            if (! $botToken) {
                $this->error('No hay token de bot global configurado en la plataforma ni en services.telegram.bot_token.');

                return self::INVALID;
            }
        }

        if ($isInfo) {
            return $this->getInfo($botToken);
        }

        return $this->set($botToken, $webhookUrl);
    }

    protected function set(string $botToken, string $webhookUrl): int
    {
        $this->info("Configurando webhook: {$webhookUrl}");

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => config('services.http_verify_tls')])
                ->post("https://api.telegram.org/bot{$botToken}/setWebhook", [
                    'url' => $webhookUrl,
                    'drop_pending_updates' => true,
                ]);

            $body = $response->json();

            if ($response->successful() && ($body['ok'] ?? false)) {
                $this->info('Webhook configurado correctamente.');

                return self::SUCCESS;
            }

            $this->error('Error al configurar el webhook: '.($body['description'] ?? 'desconocido').' (HTTP '.$response->status().')');

            return self::FAILURE;
        } catch (ConnectionException $e) {
            Log::error('Telegram setWebhook connection error', ['error' => $e->getMessage()]);
            $this->error('No se pudo conectar con la API de Telegram. Verifica tu conexión e inténtalo nuevamente.');

            return self::FAILURE;
        } catch (\Exception $e) {
            Log::error('Telegram setWebhook error', ['error' => $e->getMessage()]);
            $this->error('Error inesperado: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function getInfo(string $botToken): int
    {
        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withOptions(['verify' => config('services.http_verify_tls')])
                ->get("https://api.telegram.org/bot{$botToken}/getWebhookInfo");

            $body = $response->json();

            if ($response->successful() && ($body['ok'] ?? false)) {
                $info = $body['result'];
                $this->info('Webhook URL: '.($info['url'] ?: '(vacío — sin webhook configurado)'));
                $this->info('Pending updates: '.($info['pending_update_count'] ?? 0));
                $this->info('Último error: '.($info['last_error_message'] ?? 'ninguno'));

                return self::SUCCESS;
            }

            $this->error('Error al consultar getWebhookInfo: '.($body['description'] ?? 'desconocido'));

            return self::FAILURE;
        } catch (ConnectionException $e) {
            $this->error('No se pudo conectar con la API de Telegram. Verifica tu conexión e inténtalo nuevamente.');

            return self::FAILURE;
        }
    }
}
