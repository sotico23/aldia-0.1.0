<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Events\PaymentSuccessful;
use App\Models\PaymentConfig;
use App\Models\Pedido;
use App\Models\Transaction;
use App\Models\User;
use App\Scopes\OwnerScope;
use App\Traits\ConfirmsAppointmentFromPedido;
use App\Traits\ErpSyncTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PayPalController extends Controller
{
    use ConfirmsAppointmentFromPedido;
    use ErpSyncTrait;

    /**
     * Get PayPal credentials for the store owner.
     *
     * @return array{client_id: string, client_secret: string, mode: string}
     */
    private function getCredentials(int $ownerId): array
    {
        $config = PaymentConfig::resolveForOwner($ownerId);

        if (! $config || ! $config->paypal_active) {
            throw new \Exception('Configuración de PayPal no disponible.');
        }

        return [
            'client_id' => $config->paypal_client_id,
            'client_secret' => $config->paypal_client_secret,
            'mode' => $config->paypal_mode,
        ];
    }

    /**
     * Get the PayPal API base URL based on mode.
     */
    private function getBaseUrl(string $mode): string
    {
        return $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * Obtain an access token from PayPal.
     */
    private function getAccessToken(array $credentials): string
    {
        $baseUrl = $this->getBaseUrl($credentials['mode']);

        $response = Http::timeout(15)
            ->connectTimeout(5)
            ->asForm()
            ->withBasicAuth($credentials['client_id'], $credentials['client_secret'])
            ->post("{$baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);

        if ($response->failed()) {
            Log::error('PayPal: Failed to obtain access token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('No se pudo obtener el token de acceso de PayPal.');
        }

        return $response->json('access_token');
    }

    /**
     * Create a PayPal order and redirect the user for approval.
     */
    public function pay(int $pedidoId)
    {
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->findOrFail($pedidoId);

        if (! auth()->check() || $pedido->cliente_id !== auth()->id()) {
            abort(403, 'No autorizado.');
        }

        // Prevent double-pay on already completed orders
        if ($pedido->payment_status === 'completed') {
            $slug = $pedido->publicProfile()->withoutGlobalScope(OwnerScope::class)->first()?->slug;

            return redirect()->route('tienda.confirmacion', [
                'slug' => $slug,
                'pedidoId' => $pedido->id,
            ])->with('info', 'Este pedido ya fue pagado.');
        }

        try {
            $credentials = $this->getCredentials($pedido->owner_id ?? $pedido->user_id);
            $accessToken = $this->getAccessToken($credentials);
            $baseUrl = $this->getBaseUrl($credentials['mode']);

            // PayPal requires USD for non-supported currencies. CLP will be
            // converted by PayPal at their own rate. The original pedido
            // currency is recorded in the Transaction for accounting purposes.
            $paypalCurrency = 'USD';

            // Build the PayPal order
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->withToken($accessToken)
                ->post("{$baseUrl}/v2/checkout/orders", [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'amount' => [
                                'currency_code' => $paypalCurrency,
                                'value' => number_format((float) $pedido->total, 2, '.', ''),
                            ],
                            'reference_id' => $pedido->numero_pedido,
                            'description' => "Pedido #{$pedido->numero_pedido}",
                        ],
                    ],
                    'application_context' => [
                        'brand_name' => config('app.name', 'Marketplace'),
                        'return_url' => route('paypal.success', ['pedidoId' => $pedido->id]),
                        'cancel_url' => route('paypal.cancel', ['pedidoId' => $pedido->id]),
                        'user_action' => 'PAY_NOW',
                    ],
                ]);

            if ($response->failed()) {
                Log::error('PayPal: Failed to create order', [
                    'pedido_id' => $pedido->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->redirectWithError($pedido, 'Error al crear la orden en PayPal. Intenta de nuevo.');
            }

            $order = $response->json();

            $pedido->update([
                'payment_id' => $order['id'],
                'payment_status' => 'created',
            ]);

            // Find the approval URL
            $approveUrl = collect($order['links'] ?? [])
                ->firstWhere('rel', 'approve');

            if ($approveUrl) {
                return Inertia::location($approveUrl['href']);
            }

            Log::error('PayPal: No approval link found in order response', [
                'pedido_id' => $pedido->id,
                'order' => $order,
            ]);

            return $this->redirectWithError($pedido, 'No se encontró el enlace de aprobación de PayPal.');

        } catch (\Exception $e) {
            Log::error('PayPal: Exception during pay', [
                'pedido_id' => $pedido->id,
                'error' => $e->getMessage(),
            ]);

            return $this->redirectWithError($pedido, 'Error al procesar el pago con PayPal: '.$e->getMessage());
        }
    }

    /**
     * Handle the return from PayPal after user approval. Captures the payment.
     */
    public function success(Request $request, int $pedidoId): RedirectResponse
    {
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->findOrFail($pedidoId);

        if (! auth()->check() || $pedido->cliente_id !== auth()->id()) {
            abort(403, 'No autorizado.');
        }

        $paypalOrderId = $request->query('token');

        if (! $paypalOrderId) {
            return $this->redirectWithError($pedido, 'Token de PayPal no recibido.');
        }

        try {
            $credentials = $this->getCredentials($pedido->owner_id ?? $pedido->user_id);
            $accessToken = $this->getAccessToken($credentials);
            $baseUrl = $this->getBaseUrl($credentials['mode']);

            $response = Http::timeout(30)
                ->connectTimeout(5)
                ->withToken($accessToken)
                ->withBody('{}', 'application/json')
                ->post("{$baseUrl}/v2/checkout/orders/{$paypalOrderId}/capture");

            if ($response->failed()) {
                Log::error('PayPal: Failed to capture payment', [
                    'pedido_id' => $pedido->id,
                    'paypal_order_id' => $paypalOrderId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->redirectWithError($pedido, 'No se pudo capturar el pago de PayPal.');
            }

            $capture = $response->json();
            $captureStatus = $capture['status'] ?? 'UNKNOWN';

            if ($captureStatus !== 'COMPLETED') {
                Log::warning('PayPal: Capture status not COMPLETED', [
                    'pedido_id' => $pedido->id,
                    'status' => $captureStatus,
                    'capture' => $capture,
                ]);

                $pedido->update([
                    'payment_status' => strtolower($captureStatus),
                    'payment_data' => $capture,
                ]);

                return $this->redirectWithError($pedido, "El pago no fue completado. Estado: {$captureStatus}");
            }

            $wasAlreadyProcessed = false;

            DB::transaction(function () use ($pedido, $credentials, $capture, $paypalOrderId, &$wasAlreadyProcessed) {
                $lockedPedido = Pedido::withoutGlobalScope(OwnerScope::class)
                    ->where('id', $pedido->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedPedido->payment_status === 'completed') {
                    $wasAlreadyProcessed = true;

                    return;
                }

                $capturedAmount = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? null;
                if ($capturedAmount !== null && round((float) $capturedAmount, 2) !== round((float) $lockedPedido->total, 2)) {
                    Log::warning('PayPal: captured amount differs from expected', [
                        'pedido_id' => $lockedPedido->id,
                        'expected' => $lockedPedido->total,
                        'captured' => $capturedAmount,
                    ]);
                }

                $lockedPedido->update([
                    'payment_status' => 'completed',
                    'estado' => 'confirmado',
                    'payment_data' => $capture,
                    'fecha_confirmacion' => now(),
                ]);

                $transaction = $this->recordTransaction($lockedPedido, $credentials, $capture, $paypalOrderId);

                $this->syncPedidoToErp($lockedPedido);

                $this->confirmAppointmentFromPedido($lockedPedido);

                event(new PaymentSuccessful($transaction));
            });

            $slug = $pedido->publicProfile()->withoutGlobalScope(OwnerScope::class)->first()?->slug;

            if ($wasAlreadyProcessed) {
                return redirect()->route('tienda.confirmacion', [
                    'slug' => $slug,
                    'pedidoId' => $pedido->id,
                ])->with('info', 'Este pedido ya fue procesado.');
            }

            return redirect()->route('tienda.confirmacion', [
                'slug' => $slug,
                'pedidoId' => $pedido->id,
            ])->with('success', '¡Pago realizado exitosamente con PayPal!');

        } catch (\Exception $e) {
            Log::error('PayPal: Exception during capture', [
                'pedido_id' => $pedido->id,
                'error' => $e->getMessage(),
            ]);

            return $this->redirectWithError($pedido, 'Error al capturar el pago: '.$e->getMessage());
        }
    }

    /**
     * Handle cancellation from PayPal.
     */
    public function cancel(int $pedidoId): RedirectResponse
    {
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->findOrFail($pedidoId);

        if (! auth()->check() || $pedido->cliente_id !== auth()->id()) {
            abort(403, 'No autorizado.');
        }

        // Verify with PayPal API if order was actually cancelled
        $paypalOrderId = $pedido->payment_id;
        if ($paypalOrderId) {
            try {
                $credentials = $this->getCredentials($pedido->owner_id ?? $pedido->user_id);
                $accessToken = $this->getAccessToken($credentials);
                $baseUrl = $this->getBaseUrl($credentials['mode']);

                $response = Http::timeout(15)
                    ->connectTimeout(5)
                    ->withToken($accessToken)
                    ->get("{$baseUrl}/v2/checkout/orders/{$paypalOrderId}");

                if ($response->successful()) {
                    $order = $response->json();
                    $orderStatus = $order['status'] ?? 'UNKNOWN';

                    // If PayPal says approved, redirect to success handler
                    if ($orderStatus === 'APPROVED' || $orderStatus === 'COMPLETED') {
                        return redirect()->route('paypal.success', ['pedidoId' => $pedido->id, 'token' => $paypalOrderId]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('PayPal cancel: could not verify order status', [
                    'pedido_id' => $pedido->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DB::transaction(function () use ($pedido) {
            $pedido->update(['payment_status' => 'cancelled']);
        });

        $slug = $pedido->publicProfile()->withoutGlobalScope(OwnerScope::class)->first()?->slug;

        return redirect()->route('marketplace.show', $slug)
            ->with('error', 'El pago con PayPal ha sido cancelado. Puedes intentarlo de nuevo.');
    }

    /**
     * Helper to redirect back to the store with an error message.
     */
    private function redirectWithError(Pedido $pedido, string $message): RedirectResponse
    {
        $slug = $pedido->publicProfile()->withoutGlobalScope(OwnerScope::class)->first()?->slug ?? 'marketplace';

        return redirect()->route('marketplace.show', $slug)
            ->with('error', $message);
    }

    private function recordTransaction(Pedido $pedido, array $credentials, array $capture, string $paypalOrderId): Transaction
    {
        $captureData = $capture['purchase_units'][0]['payments']['captures'][0] ?? [];
        $captureId = $captureData['id'] ?? $paypalOrderId;
        $amount = $captureData['amount']['value'] ?? $pedido->total;
        // Use the pedido's original currency for accounting accuracy
        $currency = $pedido->currency ?? $this->resolveCurrencyForPedido($pedido);
        $breakdown = $captureData['seller_receivable_breakdown'] ?? [];
        $fee = (float) ($breakdown['paypal_fee']['value'] ?? 0);
        $netAmount = (float) ($breakdown['net_amount']['value'] ?? $amount);

        return Transaction::firstOrCreate(
            [
                'gateway' => 'paypal',
                'gateway_transaction_id' => $captureId,
            ],
            [
                'business_id' => $pedido->owner_id ?? $pedido->business_id,
                'pedido_id' => $pedido->id,
                'user_id' => $pedido->cliente_id,
                'type' => 'customer_payment',
                'status' => 'approved',
                'currency' => $currency,
                'amount' => (float) $amount,
                'fee' => $fee,
                'net_amount' => $netAmount,
                'metadata' => [
                    'numero_pedido' => $pedido->numero_pedido,
                    'paypal_order_id' => $paypalOrderId,
                ],
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
