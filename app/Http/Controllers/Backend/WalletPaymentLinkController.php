<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\WalletPaymentLinkRequest;
use App\Models\WalletPaymentRequest;
use App\Services\WalletService;
use App\Services\WalletTransferService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletPaymentLinkController extends Controller
{
    public function __construct(private readonly WalletTransferService $transfers, private readonly WalletService $wallets) {}

    public function store(WalletPaymentLinkRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $payment = $this->transfers->requestPayment($request->user(), null, (string) $data['amount'], $data['request_key'], $data['note'] ?? null);

        return to_route('billetera.enlaces.show', ['paymentRequest' => $payment->share_token]);
    }

    public function show(Request $request, WalletPaymentRequest $paymentRequest): Response
    {
        $wallet = $this->wallets->walletFor($request->user());
        $owner = (int) $paymentRequest->requester_wallet_id === (int) $wallet->id;
        abort_unless($owner || $paymentRequest->payer_wallet_id === null || (int) $paymentRequest->payer_wallet_id === (int) $wallet->id, 403);
        $paymentRequest->load('requesterWallet.user:id,name,is_active');
        $url = route('billetera.enlaces.show', ['paymentRequest' => $paymentRequest->share_token]);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(320, 4), new SvgImageBackEnd)))->writeString($url);

        return Inertia::render('Backend/Wallets/PaymentLink', [
            'payment' => [
                'id' => $paymentRequest->id, 'token' => $paymentRequest->share_token,
                'amount' => $paymentRequest->amount, 'currency' => $paymentRequest->currency,
                'note' => $paymentRequest->note, 'status' => $paymentRequest->status,
                'recipient' => $paymentRequest->requesterWallet->user->name,
                'account_number' => $paymentRequest->requesterWallet->account_number,
                'is_owner' => $owner, 'url' => $url, 'qr_code' => 'data:image/svg+xml;base64,'.base64_encode($svg),
                'can_pay' => ! $owner && $paymentRequest->status === 'pending'
                    && $wallet->status === 'active' && $paymentRequest->requesterWallet->status === 'active'
                    && (bool) $paymentRequest->requesterWallet->user->is_active
                    && $wallet->currency === $paymentRequest->currency
                    && $paymentRequest->requesterWallet->currency === $paymentRequest->currency
                    && BigDecimal::of($wallet->balance)->isGreaterThanOrEqualTo($paymentRequest->amount),
            ],
            'wallet' => ['balance' => $wallet->balance, 'currency' => $wallet->currency],
        ]);
    }

    public function pay(Request $request, WalletPaymentRequest $paymentRequest): RedirectResponse
    {
        $this->transfers->payLink($request->user(), $paymentRequest);

        return back()->with('success', 'Pago realizado correctamente.');
    }
}
