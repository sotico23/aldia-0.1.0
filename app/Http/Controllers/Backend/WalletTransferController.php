<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\WalletRecipientRequest;
use App\Http\Requests\WalletTransferRequest;
use App\Services\WalletTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class WalletTransferController extends Controller
{
    public function __construct(private readonly WalletTransferService $service) {}

    public function recipient(WalletRecipientRequest $request): JsonResponse
    {
        $wallet = $this->service->recipient($request->user(), $request->validated('account_number'));

        return response()->json(['account_number' => $wallet->account_number, 'name' => $wallet->user->name, 'currency' => $wallet->currency]);
    }

    public function store(WalletTransferRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->service->transfer($request->user(), $data['account_number'], (string) $data['amount'], $data['request_key'], $data['note'] ?? null);

        return back()->with('success', 'Transferencia realizada correctamente.');
    }
}
