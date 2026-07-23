<?php

namespace App\Http\Controllers\Backend;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Transaction;
use App\Models\WebpayTransaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class WebpayTransactionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [];
    }

    public function index()
    {
        $ownerId = Auth::user()->getOwnerId();

        $webpayTransactions = WebpayTransaction::where('owner_id', $ownerId)
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'pasarela' => 'webpay',
                'buy_order' => $t->buy_order,
                'amount' => $t->amount,
                'status' => $t->status,
                'created_at' => $t->created_at,
                'details' => $t->transbank_response,
                'type' => 'webpay_transaction',
            ]);

        $mercadopagoPayments = Pedido::where('owner_id', $ownerId)
            ->where('metodo_pago', 'mercadopago')
            ->whereNotNull('payment_data')
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'pasarela' => 'mercadopago',
                'buy_order' => $p->numero_pedido,
                'amount' => $p->total,
                'status' => $this->mapMercadoPagoStatus($p->payment_status, $p->estado),
                'created_at' => $p->created_at,
                'details' => $p->payment_data,
                'type' => 'pedido',
            ]);

        $paypalPayments = Pedido::where('owner_id', $ownerId)
            ->where('metodo_pago', 'paypal')
            ->whereNotNull('payment_data')
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'pasarela' => 'paypal',
                'buy_order' => $p->numero_pedido,
                'amount' => $p->total,
                'status' => $this->mapPayPalStatus($p->payment_status, $p->estado),
                'created_at' => $p->created_at,
                'details' => $p->payment_data,
                'type' => 'pedido',
            ]);

        $unifiedTransactions = Transaction::where('business_id', $ownerId)
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'pasarela' => $t->gateway,
                'buy_order' => $t->gateway_transaction_id,
                'amount' => $t->amount,
                'status' => $t->status,
                'created_at' => $t->created_at,
                'details' => $t->metadata,
                'type' => $t->type,
                'fee' => $t->fee,
                'net_amount' => $t->net_amount,
                'uuid' => $t->uuid,
            ]);

        $allTransactions = $webpayTransactions
            ->concat($mercadopagoPayments)
            ->concat($paypalPayments)
            ->concat($unifiedTransactions)
            ->sortByDesc('created_at')
            ->values();

        $paginated = new LengthAwarePaginator(
            $allTransactions->forPage(request()->page ?? 1, 15),
            $allTransactions->count(),
            15,
            request()->page ?? 1
        );

        return Inertia::render('Backend/Pagos/WebpayMovimientos', [
            'transactions' => $paginated,
        ]);
    }

    private function mapMercadoPagoStatus(PaymentStatus|string|null $paymentStatus, ?string $estado): string
    {
        if (in_array($estado, ['completado', 'confirmado'])) {
            return 'approved';
        }
        if (in_array($estado, ['cancelado', 'rechazado'])) {
            return 'failed';
        }
        if ($paymentStatus === 'pending' || $paymentStatus?->value === 'pending' || $estado === 'pendiente') {
            return 'pending';
        }

        return $estado ?? 'pending';
    }

    private function mapPayPalStatus(PaymentStatus|string|null $paymentStatus, ?string $estado): string
    {
        if (in_array($estado, ['completado', 'confirmado'])) {
            return 'approved';
        }
        if (in_array($estado, ['cancelado', 'rechazado'])) {
            return 'failed';
        }
        if ($paymentStatus === 'pending' || $paymentStatus?->value === 'pending' || $estado === 'pendiente') {
            return 'pending';
        }

        return $estado ?? 'pending';
    }
}
