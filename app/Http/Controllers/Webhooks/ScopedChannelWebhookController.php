<?php

namespace App\Http\Controllers\Webhooks;

use App\Events\WebhookReceived;
use App\Http\Controllers\Controller;
use App\Services\ChannelConfigService;
use App\Services\TelegramWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ScopedChannelWebhookController extends Controller
{
    public function __construct(
        private readonly ChannelConfigService $channelConfig,
        private readonly TelegramWebhookService $telegramWebhook,
    ) {}

    public function globalTelegram(Request $request): JsonResponse
    {
        $config = $this->channelConfig->globalTelegram();

        return $this->handleTelegram($request, $config, ChannelConfigService::SCOPE_GLOBAL);
    }

    public function tenantTelegram(Request $request, string $key): JsonResponse
    {
        $config = $this->channelConfig->telegramConfigByWebhookKey($key);

        if (! $config || ($config['mode'] ?? null) !== ChannelConfigService::MODE_CUSTOM) {
            return response()->json(['message' => 'Webhook no encontrado.'], 404);
        }

        return $this->handleTelegram(
            $request,
            $config,
            $this->channelConfig->botScopeForOwner((int) $config['owner_id'])
        );
    }

    public function globalWhatsApp(Request $request): Response|JsonResponse
    {
        return $this->handleWhatsApp($request, $this->channelConfig->globalWhatsApp());
    }

    public function tenantWhatsApp(Request $request, string $key): Response|JsonResponse
    {
        $config = $this->channelConfig->whatsAppConfigByWebhookKey($key);

        if (! $config || ($config['mode'] ?? null) !== ChannelConfigService::MODE_CUSTOM) {
            return response()->json(['message' => 'Webhook no encontrado.'], 404);
        }

        return $this->handleWhatsApp($request, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function handleTelegram(Request $request, array $config, string $botScope): JsonResponse
    {
        $secret = $config['webhook_secret'] ?? null;

        if (! ($config['is_active'] ?? false) || ! filled($secret)) {
            return response()->json(['message' => 'Webhook no disponible.'], 404);
        }

        if (! hash_equals((string) $secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''))) {
            return response()->json(['message' => 'Firma inválida.'], 403);
        }

        $result = $this->telegramWebhook->processWebhook($request->all(), $config, $botScope);

        Log::debug('Scoped Telegram webhook processed', [
            'scope' => $botScope,
            'outcome' => $result['status'] ?? 'ok',
        ]);

        return response()->json([
            'status' => 'ok',
            'handled_by' => $result['handled_by'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function handleWhatsApp(Request $request, array $config): Response|JsonResponse
    {
        if (! ($config['is_active'] ?? false)) {
            return response()->json(['message' => 'Webhook no disponible.'], 404);
        }

        if ($request->isMethod('GET')) {
            $query = $request->query->all();
            $mode = $query['hub.mode'] ?? $query['hub_mode'] ?? data_get($query, 'hub.mode');
            $verifyToken = $query['hub.verify_token'] ?? $query['hub_verify_token'] ?? data_get($query, 'hub.verify_token');
            $challenge = $query['hub.challenge'] ?? $query['hub_challenge'] ?? data_get($query, 'hub.challenge');

            if (
                $mode !== 'subscribe'
                || ! filled($config['verify_token'] ?? null)
                || ! hash_equals((string) $config['verify_token'], (string) $verifyToken)
                || ! is_string($challenge)
            ) {
                return response()->json(['message' => 'Verificación inválida.'], 403);
            }

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        if (! $request->isMethod('POST')) {
            return response()->json(['message' => 'Método no soportado.'], 405);
        }

        $appSecret = $config['app_secret'] ?? null;

        if (! filled($appSecret)) {
            return response()->json(['message' => 'Webhook no disponible.'], 404);
        }

        $signature = (string) $request->header('X-Hub-Signature-256', '');
        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $appSecret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Firma inválida.'], 403);
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Payload JSON inválido.'], 400);
        }

        if (! $this->matchesPhoneNumber($payload, (string) ($config['phone_number_id'] ?? ''))) {
            return response()->json(['message' => 'El webhook no corresponde al número configurado.'], 403);
        }

        $eventType = $payload['entry'][0]['changes'][0]['field'] ?? 'unknown';
        $ownerId = ($config['mode'] ?? null) === ChannelConfigService::MODE_CUSTOM
            ? (int) $config['owner_id']
            : null;

        event(new WebhookReceived('whatsapp', $eventType, $payload, $ownerId));

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function matchesPhoneNumber(array $payload, string $expectedPhoneNumberId): bool
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $phoneNumberId = $change['value']['metadata']['phone_number_id'] ?? null;

                if (
                    $phoneNumberId !== null
                    && $expectedPhoneNumberId !== ''
                    && ! hash_equals($expectedPhoneNumberId, (string) $phoneNumberId)
                ) {
                    return false;
                }
            }
        }

        return true;
    }
}
