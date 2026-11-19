<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\CommissionRuleRequest;
use App\Http\Requests\WalletRechargeRequest;
use App\Models\CommissionRule;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * Panel Master: listado de billeteras de cajeros con saldo, estado y
     * comisiones generadas, junto al libro mayor global filtrable.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $ownerId = (int) $user->getOwnerId();
        $scopedOwnerId = $user->highestRoleLevel() === 0 ? null : $ownerId;

        $wallets = Wallet::query()
            ->with(['user'])
            ->when($scopedOwnerId !== null, fn ($query) => $query->whereHas(
                'user',
                fn ($userQuery) => $userQuery->where('owner_id', $scopedOwnerId),
            ))
            ->orderByDesc('balance')
            ->get();

        $walletsData = $wallets->map(
            fn (Wallet $wallet): array => $this->transformWallet($wallet)
        );

        $reglas = CommissionRule::query()
            ->with('cashier')
            ->when($scopedOwnerId !== null, fn ($query) => $query->where('tenant_id', $scopedOwnerId))
            ->orderBy('id')
            ->get()
            ->map(fn (CommissionRule $regla): array => [
                'id' => $regla->id,
                'tenant_id' => $regla->tenant_id,
                'user_id' => $regla->user_id,
                'commission_type' => (string) $regla->commission_type,
                'commission_value' => (float) $regla->commission_value,
                'min_sale_amount' => (float) $regla->min_sale_amount,
                'is_active' => (bool) $regla->is_active,
                'cashier' => $regla->cashier !== null ? [
                    'id' => $regla->cashier->id,
                    'name' => $regla->cashier->name,
                    'email' => $regla->cashier->email,
                ] : null,
            ]);

        $transacciones = $this->walletService->getTransacciones(
            walletId: $request->integer('wallet_id') ?: null,
            type: $request->string('type')->toString() ?: null,
            category: $request->string('category')->toString() ?: null,
            cajeroId: $request->integer('cashier_id') ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
            ownerId: $scopedOwnerId,
        );

        $transaccionesPropiedad = $transacciones->toArray();

        $transaccionesPropiedad['meta'] = [
            'from' => $transacciones->firstItem(),
            'to' => $transacciones->lastItem(),
            'total' => (int) $transacciones->total(),
        ];

        return Inertia::render('Backend/Wallets/Index', [
            'wallets' => $walletsData,
            'cajeros' => $this->cajerosDisponibles($scopedOwnerId),
            'commission_rules' => $reglas,
            'transacciones' => $transaccionesPropiedad,
            'filtros' => [
                'cashier_id' => $request->integer('cashier_id') ?: '',
                'type' => $request->string('type')->toString(),
                'category' => $request->string('category')->toString(),
                'desde' => $request->string('desde')->toString(),
                'hasta' => $request->string('hasta')->toString(),
            ],
            'tenant_id' => $ownerId,
        ]);
    }

    /**
     * Historial global de transacciones con filtros por cajero, tipo de
     * movimiento y rango de fechas. Redirige al panel con los filtros
     * aplicados como query string.
     */
    public function historial(Request $request): RedirectResponse
    {
        return redirect()->route('billeteras.index', $request->query());
    }

    /**
     * Recarga Master hacia la billetera de un cajero (con motivo/comprobante).
     */
    public function recargas(WalletRechargeRequest $request, Wallet $wallet): RedirectResponse
    {
        $validated = $request->validated();

        $transaction = $this->walletService->rechargeFromMaster(
            wallet: $wallet,
            amount: (float) $validated['amount'],
            masterUser: Auth::user(),
            note: isset($validated['note']) ? (string) $validated['note'] : null,
        );

        return redirect()
            ->back()
            ->with('success', 'Recarga de billetera exitosa. Transacción: '.$transaction->id);
    }

    /**
     * Recarga Master a un cajero que aún no tiene billetera. La billetera se
     * crea de forma diferida para que el master pueda cargar saldo sin esperar
     * a que el cajero abra su propio panel.
     */
    public function recargasCajero(WalletRechargeRequest $request, User $cajero): RedirectResponse
    {
        $validated = $request->validated();

        $wallet = Wallet::query()->firstOrCreate(
            ['user_id' => $cajero->id],
            [
                'balance' => 0,
                'currency' => 'CLP',
                'status' => 'active',
                'allow_overdraft' => false,
            ],
        );

        $transaction = $this->walletService->rechargeFromMaster(
            wallet: $wallet,
            amount: (float) $validated['amount'],
            masterUser: Auth::user(),
            note: isset($validated['note']) ? (string) $validated['note'] : null,
        );

        return redirect()
            ->back()
            ->with('success', 'Recarga de billetera exitosa. Transacción: '.$transaction->id);
    }

    /**
     * Crea o actualiza una regla de comisión (general o por cajero).
     */
    public function commissionRules(CommissionRuleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $tenantId = (int) ($validated['tenant_id'] ?? $request->user()->getOwnerId());
        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;

        $regla = CommissionRule::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if ($regla === null) {
            CommissionRule::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'commission_type' => $validated['commission_type'],
                'commission_value' => (float) $validated['commission_value'],
                'min_sale_amount' => (float) ($validated['min_sale_amount'] ?? 0),
                'is_active' => (bool) ($validated['is_active'] ?? true),
            ]);

            return redirect()
                ->back()
                ->with('success', 'Regla de comisión creada correctamente.');
        }

        $regla->update([
            'commission_type' => $validated['commission_type'],
            'commission_value' => (float) $validated['commission_value'],
            'min_sale_amount' => (float) ($validated['min_sale_amount'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->back()
            ->with('success', 'Regla de comisión actualizada correctamente.');
    }

    /**
     * Usuarios activos que pueden recibir una recarga. Incluye la billetera (si existe)
     * para que el modal muestre el saldo y se pueda recargar sin ambigüedad.
     *
     * @return array<int, array{user_id: int, wallet_id: int|null, name: string, email: string, balance: float, currency: string, status: string|null}>
     */
    private function cajerosDisponibles(?int $ownerId): array
    {
        return User::query()
            ->with('wallet')
            ->where('is_active', true)
            ->when($ownerId !== null, fn ($query) => $query->where('owner_id', $ownerId))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'user_id' => $user->id,
                'wallet_id' => $user->wallet?->id,
                'name' => $user->name,
                'email' => (string) $user->email,
                'balance' => (float) ($user->wallet?->balance ?? 0),
                'currency' => (string) ($user->wallet?->currency ?? 'CLP'),
                'status' => $user->wallet?->status,
            ])
            ->all();
    }

    /**
     * Normaliza un wallet para la serialización de Inertia.
     *
     * @return array<string, mixed>
     */
    private function transformWallet(Wallet $wallet): array
    {
        $comisiones = (float) WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->where('category', 'sale_commission')
            ->sum('amount');

        return [
            'id' => $wallet->id,
            'account_number' => $wallet->account_number,
            'user_id' => $wallet->user_id,
            'balance' => (float) $wallet->balance,
            'currency' => $wallet->currency,
            'status' => (string) $wallet->status,
            'allow_overdraft' => (bool) $wallet->allow_overdraft,
            'cashier' => $wallet->user !== null ? [
                'id' => $wallet->user->id,
                'name' => $wallet->user->name,
                'email' => $wallet->user->email,
            ] : null,
            'total_comisiones' => $comisiones,
        ];
    }
}
