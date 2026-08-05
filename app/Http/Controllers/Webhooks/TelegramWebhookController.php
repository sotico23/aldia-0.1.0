<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\TelegramWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private readonly TelegramWebhookService $telegramWebhookService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $result = $this->telegramWebhookService->processWebhook($request->input());

        return response()->json($result);
    }
}
