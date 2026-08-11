<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ChannelCredential;
use App\Services\TelegramWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramWebhookService $telegramWebhookService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        // El detalle del resultado (linked / invalid_token / missing_token) se
        // registra en los logs; Telegram solo necesita un 200 OK para no
        // reintentar el envío en bucle.
        $result = $this->telegramWebhookService->processWebhook($request->input());

        Log::debug('Telegram webhook processed', [
            'outcome' => $result['status'] ?? 'ok',
        ]);

        return response()->json([
            'status' => 'ok',
            'handled_by' => $result['handled_by'] ?? null,
        ], 200);
    }

    /**
     * Permite a n8n verificar si un chat_id de Telegram está vinculado a un
     * owner/tenant. Un chat_id NO null en channel_credentials es la señal de
     * vinculación activa (la columna is_telegram_active no existe en el esquema).
     *
     * Soporta de forma transparente tanto el payload estándar de Telegram
     * (message.chat.id) como el payload plano de prueba de conexión (chat_id
     * en la raíz), incluyendo wrappers producidos por n8n (body / data, con el
     * contenido opcionalmente serializado como JSON string).
     */
    public function checkLinking(Request $request): JsonResponse
    {
        $chatId = $this->extractChatId($request->input());

        if (! $chatId) {
            return response()->json([
                'is_linked' => false,
                'owner_id' => null,
            ], 422);
        }

        $credential = ChannelCredential::where('telegram_chat_id', (string) $chatId)->first();

        return response()->json([
            'is_linked' => $credential !== null,
            'owner_id' => $credential?->owner_id,
        ]);
    }

    private function extractChatId(array $payload): mixed
    {
        $payload = $this->unwrap($payload);

        if (isset($payload['chat_id']) && $payload['chat_id'] !== '') {
            return $payload['chat_id'];
        }

        return $payload['message']['chat']['id'] ?? null;
    }

    private function unwrap(array $payload): array
    {
        $payload = $payload['body'] ?? $payload['data'] ?? $payload;

        if (is_string($payload)) {
            $decoded = json_decode($payload, true);

            if (is_array($decoded)) {
                return $decoded;
            }

            return [];
        }

        return $payload;
    }
}
