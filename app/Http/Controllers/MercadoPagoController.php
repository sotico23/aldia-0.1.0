<?php

namespace App\Http\Controllers;

use App\Enums\Currency;
use App\Events\PaymentSuccessful;
use App\Models\Country;
use App\Models\PaymentConfig;
use App\Models\Pedido;
use App\Models\Transaction;
use App\Models\User;
use App\Scopes\OwnerScope;
use App\Traits\ConfirmsAppointmentFromPedido;
use App\Traits\ErpSyncTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class MercadoPagoController extends Controller
{
    use ConfirmsAppointmentFromPedido;
    use ErpSyncTrait;

    protected function getCredentials(int $ownerId)
    {
        $config = PaymentConfig::resolveForOwner($ownerId);

        if (! $config || ! $config->mercadopago_active || ! $config->mercadopago_access_token) {
            throw new \Exception('La configuración de MercadoPago no está completa o activa.');
        }

        return [
            'access_token' => $config->mercadopago_access_token,
            'mode' => $config->mercadopago_mode, // live | sandbox
        ];
    }

    protected function redirectWithError(Pedido $pedido, string $error)
    {
        $slug = $pedido->publicProfile()->withoutGlobalScope(OwnerScope::class)->first()?->slug;

        return redirect()->route('tienda.confirmacion', [
            'slug' => $slug,
            'pedidoId' => $pedido->id,
        ])->with('error', $error);
    }

    public function pay(int $pedidoId)
    {
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->findOrFail($pedidoId);

        if (! auth()->check() || $pedido->cliente_id !== auth()->id()) {
            abort(403, 'No autorizado.');
        }

        if ($pedido->payment_status === 'completed') {
            $slug = $pedido->publicProfile()->withoutGlobalScope(OwnerScope::class)->first()?->slug;

            return redirect()->route('tienda.confirmacion', [
                'slug' => $slug,
                'pedidoId' => $pedido->id,
            ])->with('info', 'Este pedido ya fue pagado.');
        }

        try {
            $credentials = $this->getCredentials($pedido->owner_id ?? $pedido->user_id);
            $accessToken = $credentials['access_token'];

            // Resolve currency from pedido or owner's country
            $currencyCode = $pedido->currency ?? $this->resolveCurrencyForPedido($pedido);

            $url = 'https://api.mercadopago.com/checkout/preferences';

            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->withToken($accessToken)
                ->post($url, [
                    'items' => [
                        [
                            'title' => "Pedido #{$pedido->numero_pedido}",
                            'quantity' => 1,
                            'unit_price' => (float) $pedido->total,
                            'currency_id' => $currencyCode,
                        ],
                    ],
                    'payer' => [
                        'name' => $pedido->nombre_cliente,
                        'email' => $pedido->cliente->email ?? 'correo@cliente.cl',
                    ],
                    'back_urls' => [
                        'success' => route('mercadopago.success', ['pedidoId' => $pedido->id]),
                        'failure' => route('mercadopago.failure', ['pedidoId' => $pedido->id]),
                        'pending' => route('mercadopago.pending', ['pedidoId' => $pedido->id]),
                    ],
                    'auto_return' => 'approved',
                    'external_reference' => (string) $pedido->numero_pedido,
                ]);

            if ($response->failed()) {
                Log::error('MercadoPago: Failed to create preference', [
                    'pedido_id' => $pedido->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return $this->redirectWithError($pedido, 'Error al crear el pago en MercadoPago.');
            }

            $preference = $response->json();

            $pedido->update([
                'payment_id' => $preference['id'],
                'payment_status' => 'created',
            ]);

            $initPoint = $credentials['mode'] === 'sandbox'
                ? $preference['sandbox_init_point']
                : $preference['init_point'];

            return Inertia::location($initPoint);

        } catch (\Exception $e) {
            Log::error('MercadoPago: Exception during pay', [
                'pedido_id' => $pedido->id,
                'error' => $e->getMessage(),
            ]);

            return $this->redirectWithError($pedido, 'Error al procesar el pago con MercadoPago: '.$e->getMessage());
        }
    }

    public function success(Request $request, int $pedidoId)
    {
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->findOrFail($pedidoId);

        if (! auth()->check() || $pedido->cliente_id !== auth()->id()) {
            abort(403, 'No autorizado.');
        }

        $paymentStatus = $request->query('status');

        if ($paymentStatus !== 'approved') {
            return $this->redirectWithError($pedido, 'El pago no fue aprobado por MercadoPago.');
        }

        // Verify payment status with MercadoPago API before confirming
        $paymentId = $request->query('payment_id') ?? $pedido->payment_id;
        if ($paymentId) {
            $payment = $this->fetchPaymentDetails($paymentId, $pedido->owner_id ?? $pedido->user_id);
            if ($payment && ($payment['status'] ?? '') !== 'approved') {
                Log::warning('MercadoPago success: payment not approved per API', [
                    'pedido_id' => $pedido->id,
                    'payment_id' => $paymentId,
                    'api_status' => $payment['status'] ?? 'unknown',
                ]);

                return $this->redirectWithError($pedido, 'El pago no fue aprobado por MercadoPago.');
            }
        }

        try {
            $wasAlreadyProcessed = false;

            DB::transaction(function () use ($pedido, $request, &$wasAlreadyProcessed) {
                $lockedPedido = Pedido::withoutGlobalScope(OwnerScope::class)
                    ->where('id', $pedido->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedPedido->payment_status === 'completed') {
                    $wasAlreadyProcessed = true;

                    return;
                }

                $lockedPedido->update([
                    'payment_status' => 'completed',
                    'estado' => 'confirmado',
                    'payment_data' => $request->only([
                        'id', 'status', 'status_detail', 'payment_type',
                        'payment_id', 'external_reference', 'transaction_amount',
                    ]),
                    'fecha_confirmacion' => now(),
                ]);

                $transaction = $this->recordTransaction($lockedPedido, $request);

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
            ])->with('success', '¡Pago realizado exitosamente con MercadoPago!');

        } catch (\Exception $e) {
            Log::error('MercadoPago: Exception during success sync', [
                'pedido_id' => $pedido->id,
                'error' => $e->getMessage(),
            ]);

            return $this->redirectWithError($pedido, 'Error al procesar la confirmación: '.$e->getMessage());
        }
    }

    public function failure(Request $request, int $pedidoId)
    {
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->findOrFail($pedidoId);

        if (! auth()->check() || $pedido->cliente_id !== auth()->id()) {
            abort(403, 'No autorizado.');
        }

        $paymentId = $request->query('payment_id');
        if ($paymentId) {
            $payment = $this->fetchPaymentDetails($paymentId, $pedido->owner_id ?? $pedido->user_id);
            $actualStatus = $payment['status'] ?? null;

            if ($actualStatus === 'approved') {
                return redirect()->route('mercadopago.success', ['pedidoId' => $pedido->id, 'payment_id' => $paymentId, 'status' => 'approved']);
            }
        }

        DB::transaction(function () use ($pedido, $request) {
            $pedido->update([
                'payment_status' => 'failed',
                'estado' => 'cancelado',
                'payment_data' => $request->only([
                    'id', 'status', 'status_detail', 'payment_type',
                    'payment_id', 'external_reference', 'transaction_amount',
                ]),
            ]);
        });

        return $this->redirectWithError($pedido, 'El pago fue rechazado por MercadoPago o cancelado.');
    }

    public function pending(Request $request, int $pedidoId)
    {
        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->findOrFail($pedidoId);

        if (! auth()->check() || $pedido->cliente_id !== auth()->id()) {
            abort(403, 'No autorizado.');
        }

        $paymentId = $request->query('payment_id');
        if ($paymentId) {
            $payment = $this->fetchPaymentDetails($paymentId, $pedido->owner_id ?? $pedido->user_id);
            $actualStatus = $payment['status'] ?? null;

            if ($actualStatus === 'approved') {
                return redirect()->route('mercadopago.success', ['pedidoId' => $pedido->id, 'payment_id' => $paymentId, 'status' => 'approved']);
            }
        }

        DB::transaction(function () use ($pedido, $request) {
            $pedido->update([
                'payment_status' => 'pending',
                'payment_data' => $request->only([
                    'id', 'status', 'status_detail', 'payment_type',
                    'payment_id', 'external_reference', 'transaction_amount',
                ]),
            ]);
        });

        $slug = $pedido->publicProfile()->withoutGlobalScope(OwnerScope::class)->first()?->slug;

        return redirect()->route('tienda.confirmacion', [
            'slug' => $slug,
            'pedidoId' => $pedido->id,
        ])->with('info', 'El pago ha quedado pendiente de confirmación en MercadoPago.');
    }

    private function fetchPaymentDetails(string $paymentId, ?int $ownerId = null): ?array
    {
        $ownerId = $ownerId ?? (auth()->check() ? auth()->user()->getOwnerId() : null);
        if (! $ownerId) {
            return null;
        }
        $credentials = $this->getCredentials($ownerId);

        $response = Http::withToken($credentials['access_token'])
            ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

        if ($response->failed()) {
            Log::warning('MercadoPago: failed to fetch payment details', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json();
    }

    private function recordTransaction(Pedido $pedido, Request $request): Transaction
    {
        $paymentId = $request->query('payment_id') ?? $pedido->payment_id;
        $amount = (float) $pedido->total;
        $currencyCode = $pedido->currency ?? $this->resolveCurrencyForPedido($pedido);

        return Transaction::firstOrCreate(
            [
                'gateway' => 'mercadopago',
                'gateway_transaction_id' => $paymentId,
            ],
            [
                'business_id' => $pedido->owner_id ?? $pedido->business_id,
                'pedido_id' => $pedido->id,
                'user_id' => $pedido->cliente_id,
                'type' => 'customer_payment',
                'status' => 'approved',
                'currency' => $currencyCode,
                'amount' => $amount,
                'fee' => 0,
                'net_amount' => $amount,
                'metadata' => [
                    'numero_pedido' => $pedido->numero_pedido,
                    'mercadopago_payment_id' => $paymentId,
                ],
                'processed_at' => now(),
            ]
        );
    }

    private function resolveCurrencyForPedido(Pedido $pedido): string
    {
        $ownerId = $pedido->owner_id ?? $pedido->user_id;
        $user = User::find($ownerId);

        if ($user && $user->country) {
            return Currency::fromCountry($user->country)->value;
        }

        return Currency::default();
    }
}
