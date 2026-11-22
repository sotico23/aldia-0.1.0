<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\WalletRefundDecisionRequest;
use App\Http\Requests\WalletRefundRequest;
use App\Models\WalletTransfer;
use App\Services\WalletTransferService;
use Illuminate\Http\RedirectResponse;

class WalletRefundController extends Controller
{
    public function store(WalletRefundRequest $request, WalletTransfer $transfer, WalletTransferService $service): RedirectResponse
    {
        $service->requestRefund($request->user(), $transfer, $request->validated('reason'));

        return back()->with('success', 'Solicitud de reembolso registrada.');
    }

    public function resolve(WalletRefundDecisionRequest $request, WalletTransfer $transfer, WalletTransferService $service): RedirectResponse
    {
        $service->resolveRefund($request->user(), $transfer, $request->validated('decision'), $request->validated('response'));

        return back()->with('success', 'Solicitud de reembolso actualizada.');
    }
}
