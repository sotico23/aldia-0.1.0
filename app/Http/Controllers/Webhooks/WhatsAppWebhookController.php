<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $mode = $request->input('hub_mode');
        $verifyToken = $request->input('hub.verify_token');
        $challenge = $request->input('hub.challenge');

        if ($request->isMethod('GET') && $mode === 'subscribe' && $verifyToken === config('services.whatsapp.verify_token')) {
            return response()->json(['hub.challenge' => $challenge], 200);
        }

        if ($request->isMethod('POST')) {
            $entry = $request->input('entry', []);

            foreach ($entry as $entryItem) {
                $changes = $entryItem['changes'] ?? [];

                foreach ($changes as $change) {
                    $value = $change['value'] ?? [];
                    $field = $change['field'] ?? '';

                    if ($field === 'messages') {
                        $messages = $value['messages'] ?? [];

                        foreach ($messages as $message) {
                            $from = $message['from'] ?? null;
                            $messageType = $message['type'] ?? null;
                            $messageId = $message['id'] ?? null;

                            Log::info('WhatsApp webhook: incoming message', [
                                'from' => $from,
                                'type' => $messageType,
                                'message_id' => $messageId,
                            ]);
                        }
                    }

                    if ($field === 'message_deliveries') {
                        $deliveries = $value['statuses'] ?? [];

                        foreach ($deliveries as $delivery) {
                            Log::info('WhatsApp webhook: delivery status', [
                                'message_id' => $delivery['id'] ?? null,
                                'status' => $delivery['status'] ?? null,
                                'recipient_id' => $delivery['recipient_id'] ?? null,
                            ]);
                        }
                    }

                    if ($field === 'message_template_status_update') {
                        Log::info('WhatsApp webhook: template status update', [
                            'meta' => $value['meta'] ?? null,
                        ]);
                    }
                }
            }

            return response()->json(['status' => 'ok'], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Método no soportado.',
        ], 405);
    }
}
