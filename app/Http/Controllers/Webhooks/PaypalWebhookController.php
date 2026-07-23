<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Currency;
use App\Events\PaymentSuccessful;
use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use App\Models\Pedido;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Scopes\OwnerScope;
use App\Traits\ErpSyncTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaypalWebhookController extends Controller
{
    use ErpSyncTrait;

    protected const VERIFIED = 'VERIFIED';

    protected const UNVERIFIED = 'UNVERIFIED';

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $eventType = $payload['event_type'] ?? 'unknown';

        Log::info('PayPal webhook received', [
            'event_type' => $eventType,
            'id' => $payload['id'] ?? null,
        ]);

        if ($this->isDuplicate($payload['id'] ?? null)) {
            Log::info('PayPal webhook: duplicate ignored', [
                'id' => $payload['id'] ?? null,
            ]);

            return response()->json(['status' => 'duplicate_ignored']);
        }

        if (! $this->verifySignature($request)) {
            Log::warning('PayPal webhook: signature verification failed', [
                'event_type' => $eventType,
                'id' => $payload['id'] ?? null,
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $this->logPayload($payload);

        try {
            return match ($eventType) {
                'PAYMENT.CAPTURE.COMPLETED' => $this->handlePaymentCaptureCompleted($payload),
                'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleSubscriptionActivated($payload),
                'BILLING.SUBSCRIPTION.CANCELLED' => $this->handleSubscriptionCancelled($payload),
                'PAYMENT.CAPTURE.DENIED',
                'PAYMENT.CAPTURE.REFUNDED',
                'PAYMENT.CAPTURE.REVERSED' => $this->handlePaymentFailed($payload),
                default => $this->handleUnknownEvent($payload),
            };
        } catch (\Exception $e) {
            Log::error('PayPal webhook: error processing event', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    protected function verifySignature(Request $request): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (! $webhookId) {
            Log::critical('PayPal webhook: WEBHOOK_ID not configured — rejecting for security');

            return false;
        }

        $headers = $request->headers;
        $transmissionId = $headers->get('PAYPAL-TRANSMISSION-ID');
        $transmissionTime = $headers->get('PAYPAL-TRANSMISSION-TIME');
        $signature = $headers->get('PAYPAL-TRANSMISSION-SIG');
        $certUrl = $headers->get('PAYPAL-CERT-URL');
        $authAlgo = $headers->get('PAYPAL-AUTH-ALGO');

        if (! $transmissionId || ! $transmissionTime || ! $signature || ! $certUrl || ! $authAlgo) {
            Log::warning('PayPal webhook: missing verification headers');

            return false;
        }

        try {
            // Try to find tenant config from webhook payload
            $payload = $request->all();
            $resource = $payload['resource'] ?? [];
            $customId = $resource['custom_id'] ?? $resource['invoice_id'] ?? null;

            $config = null;
            if ($customId) {
                $pedido = Pedido::withoutGlobalScope(OwnerScope::class)
                    ->where('numero_pedido', $customId)
                    ->orWhere('id', $customId)
                    ->first();

                if ($pedido) {
                    $config = PaymentConfig::withoutGlobalScope(OwnerScope::class)
                        ->where('owner_id', $pedido->owner_id)
                        ->whereNotNull('paypal_client_id')
                        ->where('paypal_active', true)
                        ->first();
                }
            }

            // Fallback to global config if tenant not found (for backwards compatibility)
            if (! $config) {
                $config = PaymentConfig::withoutGlobalScope(OwnerScope::class)
                    ->whereNotNull('paypal_client_id')
                    ->where('paypal_active', true)
                    ->first();
            }

            if (! $config) {
                Log::warning('PayPal webhook: no payment config found for signature verification');

                return false;
            }

            $baseUrl = $config->paypal_mode === 'live'
                ? 'https://api-m.paypal.com'
                : 'https://api-m.sandbox.paypal.com';

            $response = Http::withBasicAuth(
                $config->paypal_client_id,
                $config->paypal_client_secret
            )->post("{$baseUrl}/v1/notifications/verify-webhook-signature", [
                'auth_algo' => $authAlgo,
                'cert_url' => $certUrl,
                'transmission_id' => $transmissionId,
                'transmission_sig' => $signature,
                'transmission_time' => $transmissionTime,
                'webhook_id' => $webhookId,
                'webhook_event' => $request->all(),
            ]);

            if ($response->failed()) {
                Log::error('PayPal webhook: verification API call failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $verificationStatus = $response->json('verification_status');

            return $verificationStatus === self::VERIFIED;
        } catch (\Exception $e) {
            Log::error('PayPal webhook: verification exception', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function isDuplicate(?string $eventId): bool
    {
        if (! $eventId) {
            return false;
        }

        $cacheKey = 'paypal_webhook_'.$eventId;

        if (cache()->has($cacheKey)) {
            return true;
        }

        cache()->put($cacheKey, true, now()->addHours(24));

        return false;
    }

    protected function logPayload(array $payload): void
    {
        Log::info('PayPal webhook payload', [
            'id' => $payload['id'] ?? null,
            'event_type' => $payload['event_type'] ?? null,
            'summary' => $payload['summary'] ?? null,
            'resource' => [
                'id' => $payload['resource']['id'] ?? null,
                'status' => $payload['resource']['status'] ?? null,
                'amount' => $payload['resource']['amount']['value'] ?? null,
            ],
        ]);
    }

    protected function handlePaymentCaptureCompleted(array $payload): JsonResponse
    {
        $resource = $payload['resource'];
        $captureId = $resource['id'] ?? null;
        $amount = $resource['amount']['value'] ?? 0;
        $currency = $resource['amount']['currency_code'] ?? 'USD';
        $referenceId = $resource['custom_id'] ?? $resource['invoice_id'] ?? null;

        $metadata = [
            'paypal_capture_id' => $captureId,
            'paypal_event_id' => $payload['id'] ?? null,
            'seller_protection' => $resource['seller_protection']['status'] ?? null,
        ];

        if ($referenceId) {
            $pedido = Pedido::withoutGlobalScope(OwnerScope::class)
                ->where('numero_pedido', $referenceId)
                ->orWhere('id', $referenceId)
                ->first();

            if ($pedido) {
                $transactionResult = DB::transaction(function () use ($pedido, $captureId, $amount, $currency, $metadata) {
                    $lockedPedido = Pedido::withoutGlobalScope(OwnerScope::class)
                        ->where('id', $pedido->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($lockedPedido->payment_status === 'completed') {
                        return null;
                    }

                    $expectedCurrency = $lockedPedido->currency ?? $this->resolveCurrencyForPedido($lockedPedido);
                    if ($currency !== $expectedCurrency) {
                        Log::error('PayPal webhook: currency mismatch spoofing attempt', [
                            'pedido_id' => $lockedPedido->id,
                            'expected' => $expectedCurrency,
                            'received' => $currency,
                        ]);

                        return response()->json(['error' => 'Currency mismatch'], 400);
                    }

                    $lockedPedido->update([
                        'payment_status' => 'completed',
                        'estado' => 'confirmado',
                        'payment_id' => $captureId,
                        'payment_data' => $metadata,
                        'fecha_confirmacion' => now(),
                    ]);

                    $this->syncPedidoToErp($lockedPedido);

                    $transaction = $this->recordTransaction($lockedPedido, 'paypal', $captureId, $amount, $currency);

                    event(new PaymentSuccessful($transaction));

                    return null;
                });

                if ($transactionResult instanceof JsonResponse) {
                    return $transactionResult;
                }
            } else {
                $transaction = $this->recordTransaction(null, 'paypal', $captureId, $amount, $currency, 'customer_payment', 'approved', $metadata);

                event(new PaymentSuccessful($transaction));
            }
        } else {
            $transaction = $this->recordTransaction(null, 'paypal', $captureId, $amount, $currency, 'customer_payment', 'approved', $metadata);

            event(new PaymentSuccessful($transaction));
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleSubscriptionActivated(array $payload): JsonResponse
    {
        $resource = $payload['resource'];
        $subscriptionId = $resource['id'] ?? null;
        $customId = $resource['custom_id'] ?? null;
        $planId = $resource['plan_id'] ?? null;

        Log::info('PayPal subscription activated', [
            'subscription_id' => $subscriptionId,
            'custom_id' => $customId,
            'plan_id' => $planId,
        ]);

        if ($subscriptionId && $customId) {
            $subscription = Subscription::firstOrCreate(
                ['gateway_subscription_id' => $subscriptionId],
                [
                    'business_id' => $customId,
                    'plan_id' => null,
                    'gateway' => 'paypal',
                    'status' => 'active',
                    'starts_at' => now(),
                    'metadata' => ['paypal_plan_id' => $planId],
                ]
            );

            if ($subscription->wasRecentlyCreated) {
                $subscription->recordHistory('created', [
                    'via' => 'paypal_webhook',
                    'gateway_subscription_id' => $subscriptionId,
                ]);
            }

            $subscription->update(['status' => 'active']);
            $subscription->recordHistory('activated', [
                'via' => 'paypal_webhook',
                'gateway_subscription_id' => $subscriptionId,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleSubscriptionCancelled(array $payload): JsonResponse
    {
        $resource = $payload['resource'];
        $subscriptionId = $resource['id'] ?? null;

        Log::info('PayPal subscription cancelled', [
            'subscription_id' => $subscriptionId,
        ]);

        if ($subscriptionId) {
            $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)->first();

            if ($subscription) {
                $subscription->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

                $subscription->recordHistory('cancelled', [
                    'via' => 'paypal_webhook',
                    'gateway_subscription_id' => $subscriptionId,
                ]);
            }
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handlePaymentFailed(array $payload): JsonResponse
    {
        $resource = $payload['resource'];
        $captureId = $resource['id'] ?? null;
        $amount = $resource['amount']['value'] ?? 0;
        $currency = $resource['amount']['currency_code'] ?? 'USD';

        $this->recordTransaction(null, 'paypal', $captureId, $amount, $currency, 'customer_payment', 'failed');

        return response()->json(['status' => 'ok']);
    }

    protected function handleUnknownEvent(array $payload): JsonResponse
    {
        Log::info('PayPal webhook: unknown event type', [
            'event_type' => $payload['event_type'] ?? null,
        ]);

        return response()->json(['status' => 'ignored']);
    }

    protected function recordTransaction(
        $pedido,
        string $gateway,
        ?string $gatewayTransactionId,
        float $amount,
        string $currency,
        string $type = 'customer_payment',
        string $status = 'approved',
        ?array $metadata = null
    ): ?Transaction {
        if (! $gatewayTransactionId) {
            return null;
        }

        return Transaction::firstOrCreate(
            [
                'gateway' => $gateway,
                'gateway_transaction_id' => $gatewayTransactionId,
            ],
            [
                'business_id' => $pedido?->owner_id ?? $pedido?->business_id,
                'pedido_id' => $pedido?->id,
                'user_id' => $pedido?->cliente_id,
                'type' => $type,
                'status' => $status,
                'currency' => $currency,
                'amount' => $amount,
                'fee' => 0,
                'net_amount' => $amount,
                'metadata' => $metadata,
                'processed_at' => now(),
            ]
        );
    }

    private function resolveCurrencyForPedido(Pedido $pedido): string
    {
        $ownerId = $pedido->owner_id ?? $pedido->business_id;
        $user = User::find($ownerId);

        if ($user && $user->country) {
            return Currency::fromCountry($user->country)->value;
        }

        return Currency::default();
    }
}
