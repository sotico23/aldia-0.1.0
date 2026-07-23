<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Currency;
use App\Events\PaymentSuccessful;
use App\Http\Controllers\Controller;
use App\Models\PaymentConfig;
use App\Models\Pedido;
use App\Models\Transaction;
use App\Models\User;
use App\Scopes\OwnerScope;
use App\Traits\ErpSyncTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    use ErpSyncTrait;

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $type = $payload['type'] ?? $payload['action'] ?? 'unknown';

        Log::info('MercadoPago webhook received', [
            'type' => $type,
            'data_id' => $payload['data']['id'] ?? null,
        ]);

        $eventId = $payload['id'] ?? $payload['data']['id'] ?? null;
        if ($this->isDuplicate($eventId)) {
            Log::info('MercadoPago webhook: duplicate ignored', [
                'id' => $eventId,
            ]);

            return response()->json(['status' => 'duplicate_ignored']);
        }

        if (! $this->verifyOrigin($request)) {
            Log::warning('MercadoPago webhook: origin verification failed');

            return response()->json(['error' => 'Invalid origin'], 400);
        }

        $this->logPayload($payload);

        try {
            return match ($type) {
                'payment' => $this->handlePaymentUpdated($payload),
                'payment.created' => $this->handlePaymentCreated($payload),
                'payment.updated' => $this->handlePaymentUpdated($payload),
                'merchant_order' => $this->handleMerchantOrder($payload),
                default => $this->handleUnknownEvent($payload),
            };
        } catch (\Exception $e) {
            Log::error('MercadoPago webhook: error processing', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal error'], 500);
        }
    }

    protected function verifyOrigin(Request $request): bool
    {
        $dataId = $request->input('data.id');
        if (! $dataId) {
            Log::warning('MercadoPago webhook: missing data.id in payload');

            return false;
        }

        // Fetch payment details to get external_reference (tenant identifier)
        $payment = $this->fetchPaymentDetails($dataId);
        if (! $payment) {
            Log::warning('MercadoPago webhook: could not fetch payment details for verification', [
                'data_id' => $dataId,
            ]);

            return false;
        }

        $externalRef = $payment['external_reference'] ?? null;
        if (! $externalRef) {
            Log::warning('MercadoPago webhook: missing external_reference in payment', [
                'data_id' => $dataId,
            ]);

            return false;
        }

        // Find tenant config using external_reference (pedido numero)
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)
            ->where('numero_pedido', $externalRef)
            ->orWhere('id', $externalRef)
            ->first();

        if (! $pedido) {
            Log::warning('MercadoPago webhook: pedido not found for external_reference', [
                'external_reference' => $externalRef,
            ]);

            return false;
        }

        $config = PaymentConfig::withoutGlobalScope(OwnerScope::class)
            ->where('owner_id', $pedido->owner_id)
            ->whereNotNull('mercadopago_access_token')
            ->where('mercadopago_active', true)
            ->first();

        if (! $config) {
            Log::warning('MercadoPago webhook: no payment config found for tenant', [
                'owner_id' => $pedido->owner_id,
            ]);

            return false;
        }

        $webhookSecret = $config->mercadopago_webhook_secret;
        if (! $webhookSecret) {
            Log::warning('MercadoPago webhook: no webhook secret configured for tenant, rejecting', [
                'owner_id' => $pedido->owner_id,
            ]);

            return false;
        }

        $signature = $request->header('X-MercadoPago-Signature');
        if (! $signature) {
            Log::warning('MercadoPago webhook: missing X-MercadoPago-Signature header');

            return false;
        }

        $parts = [];
        parse_str(str_replace(',', '&', $signature), $parts);
        $ts = $parts['ts'] ?? null;
        $hash = $parts['v1'] ?? null;

        if (! $ts || ! $hash) {
            Log::warning('MercadoPago webhook: invalid signature format');

            return false;
        }

        $requestId = $request->header('x-request-id') ?? '';
        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $expectedHash = hash_hmac('sha256', $manifest, $webhookSecret);

        return hash_equals($expectedHash, $hash);
    }

    protected function isDuplicate($eventId): bool
    {
        if (! $eventId) {
            return false;
        }

        $cacheKey = 'mercadopago_webhook_'.$eventId;

        if (cache()->has($cacheKey)) {
            return true;
        }

        cache()->put($cacheKey, true, now()->addHours(24));

        return false;
    }

    protected function logPayload(array $payload): void
    {
        Log::info('MercadoPago webhook payload', [
            'id' => $payload['id'] ?? null,
            'type' => $payload['type'] ?? null,
            'action' => $payload['action'] ?? null,
            'data_id' => $payload['data']['id'] ?? null,
        ]);
    }

    protected function fetchPaymentDetails(string $paymentId): ?array
    {
        $config = PaymentConfig::withoutGlobalScope(OwnerScope::class)
            ->whereNotNull('mercadopago_access_token')->first();
        if (! $config) {
            return null;
        }

        $response = Http::withToken($config->mercadopago_access_token)
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

        if ($response->failed()) {
            Log::error('MercadoPago: failed to fetch payment details', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json();
    }

    protected function handlePaymentCreated(array $payload): JsonResponse
    {
        $dataId = $payload['data']['id'] ?? null;
        if (! $dataId) {
            return response()->json(['error' => 'No data ID'], 400);
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handlePaymentUpdated(array $payload): JsonResponse
    {
        $dataId = $payload['data']['id'] ?? null;
        if (! $dataId) {
            return response()->json(['error' => 'No data ID'], 400);
        }

        $payment = $this->fetchPaymentDetails($dataId);
        if (! $payment) {
            return response()->json(['error' => 'Could not fetch payment'], 502);
        }

        $status = $payment['status'] ?? 'unknown';
        $externalRef = $payment['external_reference'] ?? null;
        $amount = $payment['transaction_amount'] ?? 0;
        $currency = $payment['currency_id'] ?? 'CLP';
        $netAmount = $payment['transaction_details']['net_received_amount'] ?? $amount;

        $metadata = [
            'mercadopago_payment_id' => $dataId,
            'status_detail' => $payment['status_detail'] ?? null,
            'payment_method' => $payment['payment_method_id'] ?? null,
            'installments' => $payment['installments'] ?? null,
        ];

        if ($status === 'approved' && $externalRef) {
            $pedido = Pedido::withoutGlobalScope(OwnerScope::class)
                ->where('numero_pedido', $externalRef)
                ->orWhere('id', $externalRef)
                ->first();

            if ($pedido) {
                $transactionResult = DB::transaction(function () use ($pedido, $dataId, $metadata, $amount, $netAmount, $currency) {
                    $lockedPedido = Pedido::withoutGlobalScope(OwnerScope::class)
                        ->where('id', $pedido->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($lockedPedido->payment_status === 'completed') {
                        return null;
                    }

                    if ((float) $amount !== (float) $lockedPedido->total) {
                        Log::error('MercadoPago webhook: amount mismatch - rejecting payment', [
                            'pedido_id' => $lockedPedido->id,
                            'expected' => $lockedPedido->total,
                            'received' => $amount,
                        ]);

                        return response()->json(['error' => 'Amount mismatch'], 400);
                    }

                    $expectedCurrency = $lockedPedido->currency ?? Currency::default();
                    if ($currency !== $expectedCurrency) {
                        Log::error('MercadoPago webhook: currency mismatch spoofing attempt', [
                            'pedido_id' => $lockedPedido->id,
                            'expected' => $expectedCurrency,
                            'received' => $currency,
                        ]);

                        return response()->json(['error' => 'Currency mismatch'], 400);
                    }

                    $lockedPedido->update([
                        'payment_status' => 'completed',
                        'estado' => 'confirmado',
                        'payment_id' => $dataId,
                        'payment_data' => $metadata,
                        'fecha_confirmacion' => now(),
                    ]);

                    $this->syncPedidoToErp($lockedPedido);

                    $transaction = Transaction::firstOrCreate(
                        [
                            'gateway' => 'mercadopago',
                            'gateway_transaction_id' => $dataId,
                        ],
                        [
                            'business_id' => $lockedPedido->owner_id ?? $lockedPedido->business_id,
                            'pedido_id' => $lockedPedido->id,
                            'user_id' => $lockedPedido->cliente_id,
                            'type' => 'customer_payment',
                            'status' => 'approved',
                            'currency' => $lockedPedido->currency ?? $this->resolveCurrencyForPedido($lockedPedido),
                            'amount' => $amount,
                            'fee' => $amount - $netAmount,
                            'net_amount' => $netAmount,
                            'metadata' => $metadata,
                            'processed_at' => now(),
                        ]
                    );

                    event(new PaymentSuccessful($transaction));

                    return null;
                });

                if ($transactionResult instanceof JsonResponse) {
                    return $transactionResult;
                }
            }
        } elseif (in_array($status, ['cancelled', 'rejected', 'refunded', 'chargeback'])) {
            Transaction::firstOrCreate(
                [
                    'gateway' => 'mercadopago',
                    'gateway_transaction_id' => $dataId,
                ],
                [
                    'type' => $status === 'refunded' ? 'refund' : ($status === 'chargeback' ? 'chargeback' : 'customer_payment'),
                    'status' => $status === 'refunded' ? 'refunded' : ($status === 'chargeback' ? 'chargeback' : 'failed'),
                    'currency' => $currency,
                    'amount' => $amount,
                    'fee' => 0,
                    'net_amount' => 0,
                    'metadata' => $metadata,
                    'processed_at' => now(),
                ]
            );
        }

        return response()->json(['status' => 'ok']);
    }

    protected function handleMerchantOrder(array $payload): JsonResponse
    {
        Log::info('MercadoPago: merchant_order webhook received', [
            'data_id' => $payload['data']['id'] ?? null,
        ]);

        return response()->json(['status' => 'ok']);
    }

    protected function handleUnknownEvent(array $payload): JsonResponse
    {
        Log::info('MercadoPago webhook: unknown event type', [
            'type' => $payload['type'] ?? null,
            'action' => $payload['action'] ?? null,
        ]);

        return response()->json(['status' => 'ignored']);
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
