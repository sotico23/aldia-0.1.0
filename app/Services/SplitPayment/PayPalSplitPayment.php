<?php

namespace App\Services\SplitPayment;

use App\Models\PaymentConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalSplitPayment implements SplitPaymentInterface
{
    /**
     * PayPal Commerce Platform split payment implementation.
     *
     * Flow:
     * 1. Configure PayPal Commerce Platform account.
     * 2. Seller (receiver) shares their PayPal Merchant ID.
     * 3. Create order with `payee` and `platform_fees` structure.
     * 4. PayPal splits: platform fee + seller net amount.
     * 5. Seller receives their portion minus fee.
     *
     * Prerequisites:
     * - PayPal Commerce Platform enabled account.
     * - Seller onboarded via PayPal Partner Referral API.
     * - Seller's PayPal Merchant ID (or email) registered.
     */
    public function createSplitPayment(array $paymentData): array
    {
        $businessId = $paymentData['business_id'] ?? null;
        $config = $businessId
            ? PaymentConfig::resolveForOwner($businessId)
            : PaymentConfig::whereNotNull('paypal_client_id')->first();

        if (! $config || ! $config->paypal_active) {
            throw new \RuntimeException('PayPal not configured for split payments.');
        }

        $baseUrl = $config->paypal_mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $pedidoId = $paymentData['pedido_id'];

        $accessToken = $this->getAccessToken($config);

        $response = Http::withToken($accessToken)
            ->post("{$baseUrl}/v2/checkout/orders", [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => $paymentData['currency'] ?? 'USD',
                            'value' => number_format((float) $paymentData['amount'], 2, '.', ''),
                        ],
                        'payee' => [
                            'merchant_id' => $paymentData['seller_id'],
                        ],
                        'payment_instruction' => [
                            'platform_fees' => [
                                [
                                    'amount' => [
                                        'currency_code' => $paymentData['currency'] ?? 'USD',
                                        'value' => number_format((float) ($paymentData['platform_fee'] ?? 0), 2, '.', ''),
                                    ],
                                ],
                            ],
                        ],
                        'description' => $paymentData['description'],
                        'custom_id' => $paymentData['external_reference'],
                        'invoice_id' => $paymentData['external_reference'],
                    ],
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'return_url' => route('paypal.success', ['pedidoId' => $pedidoId]),
                    'cancel_url' => route('paypal.cancel', ['pedidoId' => $pedidoId]),
                    'user_action' => 'PAY_NOW',
                ],
            ]);

        if ($response->failed()) {
            Log::error('PayPal Split Payment: Failed to create order', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to create PayPal split payment order.');
        }

        $result = $response->json();
        $approveUrl = collect($result['links'] ?? [])->firstWhere('rel', 'approve');

        return [
            'split_id' => $result['id'],
            'status' => 'created',
            'redirect_url' => $approveUrl['href'] ?? null,
        ];
    }

    public function processSplit(string $splitId): array
    {
        $config = PaymentConfig::whereNotNull('paypal_client_id')->first();

        if (! $config) {
            throw new \RuntimeException('PayPal not configured for split processing.');
        }

        $baseUrl = $config->paypal_mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $accessToken = $this->getAccessToken($config);

        $response = Http::withToken($accessToken)
            ->get("{$baseUrl}/v2/checkout/orders/{$splitId}");

        if ($response->failed()) {
            Log::error('PayPal Split: Failed to process split', [
                'split_id' => $splitId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to process PayPal split payment.');
        }

        $order = $response->json();
        $status = $order['status'] ?? 'UNKNOWN';

        return [
            'split_id' => $splitId,
            'status' => strtolower($status),
            'message' => 'Split payment processed on PayPal side.',
            'raw' => $order,
        ];
    }

    public function releaseFunds(string $splitId, float $amount): array
    {
        Log::info('PayPal Split Payment: Funds released automatically by PayPal', [
            'split_id' => $splitId,
            'amount' => $amount,
        ]);

        return [
            'split_id' => $splitId,
            'status' => 'released',
            'amount' => $amount,
            'message' => 'Funds are released automatically by PayPal Commerce Platform upon capture.',
        ];
    }

    public function getSplitStatus(string $splitId): array
    {
        $config = PaymentConfig::whereNotNull('paypal_client_id')->first();
        if (! $config) {
            throw new \RuntimeException('PayPal not configured.');
        }

        $baseUrl = $config->paypal_mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $accessToken = $this->getAccessToken($config);

        $response = Http::withToken($accessToken)
            ->get("{$baseUrl}/v2/checkout/orders/{$splitId}");

        if ($response->failed()) {
            throw new \RuntimeException('Failed to get PayPal split payment status.');
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

    protected function getAccessToken(PaymentConfig $config): string
    {
        $baseUrl = $config->paypal_mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        $response = Http::asForm()
            ->withBasicAuth($config->paypal_client_id, $config->paypal_client_secret)
            ->post("{$baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            throw new \RuntimeException('Failed to obtain PayPal access token.');
        }

        return $response->json('access_token');
    }
}
