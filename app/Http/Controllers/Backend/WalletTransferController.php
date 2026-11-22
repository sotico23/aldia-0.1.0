<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\WalletRecipientRequest;
use App\Http\Requests\WalletTransferRequest;
use App\Http\Requests\WalletUsernameRequest;
use App\Services\WalletService;
use App\Services\WalletTransferService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class WalletTransferController extends Controller
{
    public function __construct(private readonly WalletTransferService $service) {}

    public function recipient(WalletRecipientRequest $request): JsonResponse
    {
        $wallet = $request->validated('username') !== null
            ? $this->service->recipientByUsername($request->user(), $request->validated('username'))
            : $this->service->recipient($request->user(), $request->validated('account_number'));

        return response()->json(['account_number' => $wallet->account_number, 'name' => $wallet->user->name, 'currency' => $wallet->currency]);
    }

    public function updateUsername(WalletUsernameRequest $request, WalletService $wallets): RedirectResponse
    {
        $wallet = $wallets->walletFor($request->user());
        $wallet->username = $request->validated('username');

        try {
            $wallet->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'Este nombre de usuario ya está en uso.']);
        }

        return back()->with('success', 'Nombre de usuario actualizado.');
    }

    public function store(WalletTransferRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->service->transfer($request->user(), $data['account_number'], (string) $data['amount'], $data['request_key'], $data['note'] ?? null);

        return back()->with('success', 'Transferencia realizada correctamente.');
    }
}
