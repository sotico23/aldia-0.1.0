<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletPaymentRequest;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CajeroWalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * Vista del cajero autenticado: saldo disponible, comisiones acumuladas
     * en el mes y historial detallado de movimientos.
     */
    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $wallet = $this->walletService->walletFor($user);
        $wallet->loadMissing('user');

        $desde = $request->string('desde')->toString() ?: null;
        $hasta = $request->string('hasta')->toString() ?: null;

        $transacciones = $this->walletService->getTransacciones(
            walletId: $wallet->id,
            desde: $desde,
            hasta: $hasta,
            perPage: 10,
        );

        $comisionesMes = $this->walletService->comisionesDelCajero($user);

        $saldo = (float) $wallet->balance;

        $transaccionesPropiedad = $transacciones->toArray();

        $transaccionesPropiedad['meta'] = [
            'from' => $transacciones->firstItem(),
            'to' => $transacciones->lastItem(),
            'total' => (int) $transacciones->total(),
        ];

        return Inertia::render('Backend/Pos/CajeroWallet', [
            'payment_requests' => WalletPaymentRequest::query()
                ->where(fn ($query) => $query->where('requester_wallet_id', $wallet->id)->orWhere('payer_wallet_id', $wallet->id))
                ->with(['requesterWallet.user:id,name', 'payerWallet.user:id,name'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest('id')->paginate(10, ['*'], 'requests_page')->withQueryString()
                ->through(fn (WalletPaymentRequest $payment): array => [
                    'id' => $payment->id, 'amount' => $payment->amount, 'currency' => $payment->currency,
                    'note' => $payment->note, 'status' => $payment->status,
                    'share_token' => $payment->share_token,
                    'incoming' => (int) $payment->payer_wallet_id === (int) $wallet->id,
                    'counterparty' => (int) $payment->payer_wallet_id === (int) $wallet->id
                        ? $payment->requesterWallet->user->name : ($payment->payerWallet?->user?->name ?? 'Enlace de pago compartible'),
                    'created_at' => $payment->created_at->toIso8601String(),
                ]),
            'wallet' => [
                'id' => $wallet->id,
                'account_number' => $wallet->account_number,
                'user_id' => $wallet->user_id,
                'balance' => $saldo,
                'currency' => (string) $wallet->currency,
                'status' => (string) $wallet->status,
                'allow_overdraft' => (bool) $wallet->allow_overdraft,
                'cashier' => $wallet->user !== null ? [
                    'id' => $wallet->user->id,
                    'name' => $wallet->user->name,
                    'email' => $wallet->user->email,
                ] : null,
                'total_comisiones' => $this->walletService->totalComisionesDelWallet($wallet),
            ],
            'comisiones_mes' => $comisionesMes,
            'transacciones' => $transaccionesPropiedad,
            'filtros' => [
                'desde' => $desde ?? '',
                'hasta' => $hasta ?? '',
            ],
            'moneda' => (string) ($user->country ?? 'CLP'),
        ]);
    }
}
