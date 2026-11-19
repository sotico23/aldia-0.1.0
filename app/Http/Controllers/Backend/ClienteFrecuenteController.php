<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteFrecuenteRequest;
use App\Models\ClienteFrecuente;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClienteFrecuenteController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    public function store(ClienteFrecuenteRequest $request): RedirectResponse
    {
        $wallet = $this->walletService->walletFor($request->user());
        $wallet->clientesFrecuentes()->create($request->validated());

        return back()->with('success', 'Cliente frecuente agregado.');
    }

    public function update(ClienteFrecuenteRequest $request, ClienteFrecuente $clienteFrecuente): RedirectResponse
    {
        $this->ensureOwnedBy($request->user(), $clienteFrecuente);
        $clienteFrecuente->update($request->validated());

        return back()->with('success', 'Cliente frecuente actualizado.');
    }

    public function destroy(Request $request, ClienteFrecuente $clienteFrecuente): RedirectResponse
    {
        $this->ensureOwnedBy($request->user(), $clienteFrecuente);
        $clienteFrecuente->delete();

        return back()->with('success', 'Cliente frecuente eliminado.');
    }

    private function ensureOwnedBy(User $user, ClienteFrecuente $clienteFrecuente): void
    {
        abort_unless($user->canUsePersonalWallet(), 403);

        $wallet = $this->walletService->walletFor($user);

        abort_unless((int) $clienteFrecuente->wallet_id === (int) $wallet->id, 403);
    }
}
