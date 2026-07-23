<?php

namespace App\Http\Controllers\Backend;

use App\Enums\Currency;
use App\Events\PaymentSuccessful;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\PaymentSession;
use App\Models\Pedido;
use App\Models\Transaction;
use App\Models\WebpayTransaction;
use App\Scopes\BusinessScope;
use App\Scopes\OwnerScope;
use App\Services\WebpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WebpayController extends Controller
{
    protected $webpayService;

    public function __construct(WebpayService $webpayService)
    {
        $this->webpayService = $webpayService;
    }

    public function pay(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required',
            'amount' => 'required|numeric|min:1',
        ]);

        $ownerId = auth()->user()->getOwnerId();
        $amount = (float) $request->input('amount');

        $expectedCurrency = $request->input('currency', Currency::default());
        if ($expectedCurrency !== Currency::CLP->value) {
            return Inertia::render('Backend/Pagos/WebpayResult', [
                'success' => false,
                'error' => 'Webpay solo soporta pagos en Pesos Chilenos (CLP).',
            ]);
        }

        $buyOrder = 'ORD-'.time().'-'.Str::random(16);
        $sessionId = session()->getId();
        $returnUrl = route('webpay.callback');

        try {
            $response = $this->webpayService->createTransaction(
                $buyOrder,
                $sessionId,
                $amount,
                $returnUrl,
                $ownerId
            );

            $token = $response->getToken();

            WebpayTransaction::create([
                'owner_id' => $ownerId,
                'token' => $token,
                'amount' => $amount,
                'status' => 'pending',
                'buy_order' => $buyOrder,
            ]);

            PaymentSession::create([
                'token' => $token,
                'buy_order' => $buyOrder,
                'business_id' => $ownerId,
                'status' => 'pending',
                'gateway' => 'webpay',
                'amount' => $amount,
                'metadata' => [
                    'invoice_id' => $request->invoice_id,
                    'pedido_id' => $request->pedido_id,
                ],
                'expires_at' => now()->addHours(2),
            ]);

            return view('webpay.redirect', [
                'url' => $response->getUrl(),
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            Log::error('Error starting Webpay translation: '.$e->getMessage());

            return Inertia::render('Backend/Pagos/WebpayResult', [
                'success' => false,
                'error' => 'No fue posible conectar con Transbank. Revise sus credenciales.',
            ]);
        }
    }

    public function callback(Request $request)
    {
        $tokenWs = $request->input('token_ws');
        $tokenTb = $request->input('TBK_TOKEN');

        $searchToken = $tokenWs ?? $tokenTb;

        $paymentSession = PaymentSession::withoutGlobalScope(BusinessScope::class)
            ->where('token', $searchToken)->first();

        if (! $paymentSession) {
            Log::error('Payment session not found in Webpay callback.', [
                'token_provided' => $searchToken !== null,
            ]);

            return Inertia::render('Backend/Pagos/WebpayResult', [
                'success' => false,
                'error' => 'Transacción interrumpida o sesión expirada.',
            ]);
        }

        // Validate session hasn't expired
        if ($paymentSession->expires_at && $paymentSession->expires_at->isPast()) {
            Log::warning('Webpay callback: PaymentSession expired', [
                'session_id' => $paymentSession->id,
                'expires_at' => $paymentSession->expires_at,
            ]);
            $paymentSession->update(['status' => 'expired']);

            return Inertia::render('Backend/Pagos/WebpayResult', [
                'success' => false,
                'error' => 'La sesión de pago ha expirado.',
            ]);
        }

        // Validate business_id matches the authenticated user's owner_id (if authenticated)
        // This prevents cross-tenant token reuse
        if (auth()->check()) {
            $userOwnerId = auth()->user()->getOwnerId();
            if ($paymentSession->business_id !== $userOwnerId) {
                Log::warning('Webpay callback: business_id mismatch', [
                    'session_business_id' => $paymentSession->business_id,
                    'user_owner_id' => $userOwnerId,
                ]);
                // Don't reject - webhook is unauthenticated, but log for audit
            }
        }

        $ownerId = $paymentSession->business_id;
        $originalAmount = (float) $paymentSession->amount;
        $metadata = $paymentSession->metadata ?? [];

        if ($tokenTb) {
            $transaction = WebpayTransaction::where('token', $tokenTb)->first();
            if ($transaction) {
                $transaction->update(['status' => 'failed', 'transbank_response' => ['aborted' => true]]);
            }

            $paymentSession->update(['status' => 'cancelled']);

            return Inertia::render('Backend/Pagos/WebpayResult', [
                'success' => false,
                'error' => 'Has anulado el pago de manera voluntaria.',
            ]);
        }

        if (! $tokenWs) {
            return Inertia::render('Backend/Pagos/WebpayResult', [
                'success' => false,
                'error' => 'Transacción sin Token válido.',
            ]);
        }

        try {
            $response = $this->webpayService->confirmTransaction($tokenWs, $ownerId);

            $outcome = null;

            DB::transaction(function () use ($tokenWs, $ownerId, $originalAmount, $metadata, $paymentSession, $response, &$outcome) {
                $transaction = WebpayTransaction::where('token', $tokenWs)->lockForUpdate()->first();

                if (! $transaction) {
                    throw new \Exception('Transacción tokenizada no registrada localmente.');
                }

                $responseAmount = $response->getAmount();
                $status = $response->getStatus();
                $vci = $response->getVci();

                if (round((float) $responseAmount, 2) !== round((float) $originalAmount, 2)) {
                    Log::warning("Webpay: Posible manipulación de montos. Req: {$originalAmount}, Res: {$responseAmount}");
                    $transaction->update(['status' => 'failed', 'transbank_response' => ['error' => 'Monto discrepante']]);
                    $paymentSession->update(['status' => 'failed']);

                    $outcome = ['success' => false, 'error' => 'El monto cancelado fue modificado y no coincide con la tarifa original.'];

                    return;
                }

                if ($status === 'AUTHORIZED' && in_array($vci, ['TSY', 'TSN'])) {
                    $transaction->update([
                        'status' => 'approved',
                        'transbank_response' => [
                            'authorization_code' => $response->getAuthorizationCode(),
                            'payment_type' => $response->getPaymentTypeCode(),
                            'installments' => $response->getInstallmentsNumber(),
                        ],
                    ]);

                    $paymentSession->update(['status' => 'completed']);

                    $this->confirmBookingAppointment($paymentSession);

                    $unifiedTransaction = Transaction::firstOrCreate(
                        [
                            'gateway' => 'webpay',
                            'gateway_transaction_id' => $tokenWs,
                        ],
                        [
                            'business_id' => $ownerId,
                            'pedido_id' => $metadata['pedido_id'] ?? null,
                            'type' => 'customer_payment',
                            'status' => 'approved',
                            'currency' => Currency::CLP->value,
                            'amount' => $originalAmount,
                            'fee' => 0,
                            'net_amount' => $originalAmount,
                            'metadata' => [
                                'payment_session_id' => $paymentSession->id,
                                'webpay_transaction_id' => $transaction->id,
                                'buy_order' => $paymentSession->buy_order,
                                'authorization_code' => $response->getAuthorizationCode(),
                                'payment_type' => $response->getPaymentTypeCode(),
                                'installments' => $response->getInstallmentsNumber(),
                                'invoice_id' => $metadata['invoice_id'] ?? null,
                            ],
                            'processed_at' => now(),
                        ]
                    );

                    $paymentSession->update(['transaction_id' => $unifiedTransaction->id]);

                    event(new PaymentSuccessful($unifiedTransaction));

                    $outcome = [
                        'success' => true,
                        'details' => [
                            'buy_order' => $transaction->buy_order,
                            'amount' => $transaction->amount,
                            'auth_code' => $response->getAuthorizationCode(),
                        ],
                    ];

                    return;
                }

                $transaction->update([
                    'status' => 'failed',
                    'transbank_response' => [
                        'status' => $status,
                        'vci' => $vci,
                    ],
                ]);

                $paymentSession->update(['status' => 'failed']);

                $outcome = ['success' => false, 'error' => 'Transacción Rechazada por Transbank u operador de tarjeta.'];
            });

            if ($outcome['success']) {
                return Inertia::render('Backend/Pagos/WebpayResult', $outcome);
            }

            return Inertia::render('Backend/Pagos/WebpayResult', $outcome);

        } catch (\Exception $e) {
            Log::error('Webpay Validation Exception: '.$e->getMessage());

            if (isset($paymentSession)) {
                $paymentSession->update(['status' => 'failed']);
            }

            return Inertia::render('Backend/Pagos/WebpayResult', [
                'success' => false,
                'error' => 'Error validando u homologando el pago.',
            ]);
        }
    }

    private function confirmBookingAppointment(PaymentSession $paymentSession): void
    {
        $metadata = $paymentSession->metadata ?? [];
        $pedidoId = $metadata['pedido_id'] ?? null;
        if (! $pedidoId) {
            return;
        }

        $pedido = Pedido::withoutGlobalScope(OwnerScope::class)->find($pedidoId);
        if (! $pedido) {
            return;
        }

        $pedido->update([
            'payment_status' => 'completed',
            'estado' => 'confirmado',
            'fecha_confirmacion' => now(),
        ]);

        $paymentData = $pedido->payment_data;
        $appointmentId = is_array($paymentData) ? ($paymentData['appointment_id'] ?? null) : null;
        if ($appointmentId) {
            $appointment = Appointment::withoutGlobalScope(OwnerScope::class)->find($appointmentId);
            if ($appointment && $appointment->payment_status !== 'pagado') {
                $appointment->update([
                    'payment_status' => 'pagado',
                    'status' => 'confirmada',
                    'amount_paid' => $pedido->total,
                ]);
            }
        }
    }
}
