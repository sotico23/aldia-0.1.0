<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
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

        return response()->json(['status' => 'ok'], 200);
    }
}
