<?php

namespace App\Services\SplitPayment;

use App\Models\PaymentConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoPagoSplitPayment implements SplitPaymentInterface
{
    /**
     * MercadoPago Marketplace split payment implementation.
     *
     * Flow:
     * 1. Configure marketplace application in MercadoPago.
     * 2. Seller authorizes platform to collect on their behalf.
     * 3. Customer pays → MercadoPago splits: platform commission + seller net.
     * 4. Seller receives their portion minus commission.
     *
     * Prerequisites:
     * - MercadoPago seller account linked (via OAuth or access_token).
     * - Marketplace application configured in MercadoPago.
     * - `marketplace_mode: enabled` in payment preference.
     */
    public function createSplitPayment(array $paymentData): array
    {
        $businessId = $paymentData['business_id'] ?? null;
        $config = $businessId
            ? PaymentConfig::resolveForOwner($businessId)
            : PaymentConfig::whereNotNull('mercadopago_access_token')->first();

        if (! $config || ! $config->mercadopago_active) {
            throw new \RuntimeException('MercadoPago not configured for split payments.');
        }

        $accessToken = $config->mercadopago_access_token;
        $pedidoId = $paymentData['pedido_id'];
        $amount = $paymentData['amount'];

        $response = Http::withToken($accessToken)
            ->post('https://api.mercadopago.com/checkout/preferences', [
                'items' => [
                    [
                        'title' => $paymentData['description'],
                        'quantity' => 1,
                        'unit_price' => (float) $amount,
                        'currency_id' => $paymentData['currency'] ?? 'CLP',
                    ],
                ],
                'payer' => [
                    'email' => $paymentData['payer_email'] ?? 'payer@example.com',
                ],
                'marketplace' => $accessToken,
                'marketplace_fee' => (int) (($paymentData['platform_fee'] ?? 0) * 100),
                'external_reference' => $paymentData['external_reference'],
                'back_urls' => [
                    'success' => route('mercadopago.success', ['pedidoId' => $pedidoId]),
                    'failure' => route('mercadopago.failure', ['pedidoId' => $pedidoId]),
                    'pending' => route('mercadopago.pending', ['pedidoId' => $pedidoId]),
                ],
                'auto_return' => 'approved',
                'notification_url' => route('webhooks.mercadopago'),
            ]);

        if ($response->failed()) {
            Log::error('MercadoPago Split Payment: Failed to create preference', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to create MercadoPago split payment: '.$response->body());
        }

        $result = $response->json();

        return [
            'split_id' => $result['id'],
            'status' => 'created',
            'redirect_url' => $result['init_point'],
            'raw' => $result,
        ];
    }

    public function processSplit(string $splitId): array
    {
        $config = PaymentConfig::whereNotNull('mercadopago_access_token')->first();

        if (! $config) {
            throw new \RuntimeException('MercadoPago not configured for split processing.');
        }

        $response = Http::withToken($config->mercadopago_access_token)
            ->get("https://api.mercadopago.com/checkout/preferences/{$splitId}");

        if ($response->failed()) {
            Log::error('MercadoPago Split: Failed to process split', [
                'split_id' => $splitId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to process split payment: '.$response->body());
        }

        $preference = $response->json();
        $status = $preference['status'] ?? 'unknown';

        return [
            'split_id' => $splitId,
            'status' => $status,
            'message' => 'Split payment processed on MercadoPago side.',
            'raw' => $preference,
        ];
    }

    public function releaseFunds(string $splitId, float $amount): array
    {
        $config = PaymentConfig::whereNotNull('mercadopago_access_token')->first();

        if (! $config) {
            throw new \RuntimeException('MercadoPago not configured for split release.');
        }

        Log::info('MercadoPago Split Payment: Funds released by MercadoPago automatically', [
            'split_id' => $splitId,
            'amount' => $amount,
        ]);

        return [
            'split_id' => $splitId,
            'status' => 'released',
            'amount' => $amount,
            'message' => 'MercadoPago releases funds automatically upon payment approval.',
        ];
    }

    public function getSplitStatus(string $splitId): array
    {
        $config = PaymentConfig::whereNotNull('mercadopago_access_token')->first();
        if (! $config) {
            throw new \RuntimeException('MercadoPago not configured.');
        }

        $response = Http::withToken($config->mercadopago_access_token)
            ->get("https://api.mercadopago.com/checkout/preferences/{$splitId}");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to get split payment status.');
        }

        return $response->json();
    }

    public function calculateCommission(float $amount, string $type, float $rate, float $fixedComponent = 0): float
    {
        return match ($type) {
            'percentage' => $amount * ($rate / 100),
            'fixed' => $rate,
            'hybrid' => $amount * ($rate / 100) + $fixedComponent,
            default => 0,
        };
    }
}
