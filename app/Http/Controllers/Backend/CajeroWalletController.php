<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\ClienteFrecuente;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletPaymentRequest;
use App\Models\WalletTransfer;
use App\Services\WalletService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
            'wallet_action' => in_array($request->query('action'), ['transfer', 'request', 'link'], true)
                ? $request->query('action') : null,
            'clientes_frecuentes' => $wallet->clientesFrecuentes()
                ->orderBy('nombre')
                ->get()
                ->map(fn (ClienteFrecuente $cliente): array => [
                    'id' => $cliente->id,
                    'nombre' => $cliente->nombre,
                    'account_number' => $cliente->account_number,
                    'nota' => $cliente->nota,
                ]),
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
            'refunds' => fn () => $this->refunds($user, $wallet),
            'wallet' => [
                'id' => $wallet->id,
                'account_number' => $wallet->account_number,
                'username' => $wallet->username,
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

    private function refunds(User $user, Wallet $wallet): LengthAwarePaginator
    {
        $support = $user->hasAnyRole(['Master', 'Super Admin']);

        return WalletTransfer::query()
            ->where(function ($query) use ($wallet, $support): void {
                $query->where('sender_wallet_id', $wallet->id)->orWhere('recipient_wallet_id', $wallet->id);
                if ($support) {
                    $query->orWhere('refund_status', 'escalated');
                }
            })
            ->with(['senderWallet.user:id,name', 'recipientWallet.user:id,name'])
            ->orderByRaw("CASE WHEN refund_status = 'escalated' THEN 0 WHEN refund_status = 'pending' THEN 1 ELSE 2 END")
            ->latest('id')->paginate(10, ['*'], 'refunds_page')->withQueryString()
            ->through(function (WalletTransfer $transfer) use ($wallet, $support): array {
                $sent = (int) $transfer->sender_wallet_id === (int) $wallet->id;
                $received = (int) $transfer->recipient_wallet_id === (int) $wallet->id;
                $sender = $transfer->senderWallet->user->name;
                $recipient = $transfer->recipientWallet->user->name;

                return [
                    'id' => $transfer->id,
                    'amount' => $transfer->amount,
                    'currency' => $transfer->currency,
                    'note' => $transfer->note,
                    'created_at' => $transfer->created_at?->toIso8601String(),
                    'counterparty' => match (true) {
                        $sent => $recipient,
                        $received => $sender,
                        default => $sender.' → '.$recipient,
                    },
                    'refund_status' => $transfer->refund_status,
                    'refund_reason' => $transfer->refund_reason,
                    'refund_response' => $transfer->refund_response,
                    'can_request' => $sent && $transfer->refund_status === null,
                    'can_respond' => $received && $transfer->refund_status === 'pending',
                    'can_escalate' => $sent && $transfer->canEscalateRefund(),
                    'can_review' => $support && ! $sent && ! $received && $transfer->refund_status === 'escalated',
                ];
            });
    }
}
