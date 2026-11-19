<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\WalletPaymentDecisionRequest;
use App\Http\Requests\WalletTransferRequest;
use App\Models\WalletPaymentRequest;
use App\Services\WalletTransferService;
use Illuminate\Http\RedirectResponse;

class WalletPaymentRequestController extends Controller
{
    public function __construct(private readonly WalletTransferService $service) {}

    public function store(WalletTransferRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->service->requestPayment($request->user(), $data['account_number'], (string) $data['amount'], $data['request_key'], $data['note'] ?? null);

        return back()->with('success', 'Solicitud enviada. El otro usuario debe aceptar el pago.');
    }

    public function resolve(WalletPaymentDecisionRequest $request, WalletPaymentRequest $paymentRequest): RedirectResponse
    {
        $this->service->resolvePayment($request->user(), $paymentRequest, $request->validated('decision'));

        return back()->with('success', 'Solicitud de pago actualizada.');
    }
}
