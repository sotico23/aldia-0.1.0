<?php

namespace App\Http\Controllers\Webhooks;

use App\Events\WebhookReceived;
use App\Http\Controllers\Controller;
use App\Models\ChannelCredential;
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
            $payload = $request->all();
            $eventType = $payload['entry'][0]['changes'][0]['field'] ?? 'unknown';
            $businessId = $this->extractBusinessIdFromPayload($payload);

            event(new WebhookReceived('whatsapp', $eventType, $payload, $businessId));

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

    protected function extractBusinessIdFromPayload(array $payload): ?int
    {
        // Extract phone number from webhook payload to find the business
        $entry = $payload['entry'][0] ?? [];
        $changes = $entry['changes'][0] ?? [];
        $value = $changes['value'] ?? [];
        $metadata = $value['metadata'] ?? [];
        $phoneNumberId = $metadata['phone_number_id'] ?? null;

        if (! $phoneNumberId) {
            return null;
        }

        // Find business by WhatsApp phone number ID
        $config = ChannelCredential::where('whatsapp_phone_number_id', $phoneNumberId)
            ->whereNotNull('whatsapp_phone_number_id')
            ->first();

        return $config?->owner_id;
    }
}
