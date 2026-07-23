<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AutomationExecution;
use App\Models\ChannelCredential;
use App\Services\TelegramService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InternalAutomationSendController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'execution_id' => 'required|integer|exists:automation_executions,id',
            'business_id' => 'required|integer|exists:users,id',
            'channel' => 'required|in:telegram,whatsapp,both',
            'message' => 'required|string|max:10000',
            'to' => 'nullable|string|max:255',
        ]);

        $execution = AutomationExecution::where('id', $validated['execution_id'])
            ->where('owner_id', $validated['business_id'])
            ->firstOrFail();

        $credentials = ChannelCredential::where('owner_id', $validated['business_id'])->first();
        if (! $credentials) {
            return response()->json([
                'success' => false,
                'message' => 'No channel credentials configured for this business.',
            ], 422);
        }

        $errors = [];
        $channels = $validated['channel'] === 'both'
            ? ['telegram', 'whatsapp']
            : [$validated['channel']];

        foreach ($channels as $channel) {
            try {
                $result = match ($channel) {
                    'telegram' => $this->sendTelegram($validated['business_id'], $validated['message'], $validated['to'] ?? null),
                    'whatsapp' => $this->sendWhatsApp($validated['business_id'], $validated['message'], $validated['to'] ?? null),
                    default => ['success' => false, 'message' => "Unknown channel: {$channel}"],
                };

                if (! $result['success']) {
                    $errors[] = $result['message'] ?? "Failed to send via {$channel}";
                }
            } catch (\Throwable $e) {
                $errors[] = "{$channel}: {$e->getMessage()}";
            }
        }

        $execution->update([
            'status' => empty($errors) ? 'success' : 'partial_error',
            'output' => [
                'channel' => $validated['channel'],
                'errors' => $errors,
                'sent_at' => now()->toIso8601String(),
            ],
            'error_message' => empty($errors) ? null : implode('; ', $errors),
        ]);

        if (! empty($errors)) {
            Log::warning('Internal send partial failure', [
                'execution_id' => $validated['execution_id'],
                'business_id' => $validated['business_id'],
                'errors' => $errors,
            ]);

            return response()->json([
                'success' => false,
                'message' => implode('; ', $errors),
                'errors' => $errors,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully.',
        ]);
    }

    protected function sendTelegram(int $businessId, string $message, ?string $to): array
    {
        $service = (new TelegramService)->forOwner($businessId);
        $chatId = $to ?? config('services.telegram.default_chat_id');

        if (! $chatId) {
            return ['success' => false, 'message' => 'Telegram recipient not specified.'];
        }

        return $service->sendMessage($chatId, $message);
    }

    protected function sendWhatsApp(int $businessId, string $message, ?string $to): array
    {
        $service = (new WhatsAppService)->forOwner($businessId);
        $recipient = $to ?? config('services.whatsapp.default_to');

        if (! $recipient) {
            return ['success' => false, 'message' => 'WhatsApp recipient not specified.'];
        }

        return $service->sendMessage($recipient, $message);
    }
}
