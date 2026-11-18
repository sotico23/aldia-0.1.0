<?php

namespace App\Services;

use App\Models\CommissionRule;
use App\Models\User;
use App\Models\Venta;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    /**
     * Montos monetarios se manejan en decimales de 2 cifras (CLP sin decimales
     * en la práctica, pero se mantiene la escala fina para precisión).
     */
    private const SCALE = 2;

    /**
     * Recarga un monto desde el Master/Super Admin hacia la billetera de un cajero.
     *
     * Toda la operación (débito/credito) se ejecuta dentro de una transacción
     * con lock for update sobre el registro de Wallet para evitar condiciones
     * de carrera y registrar el libro mayor con saldo anterior/posterior.
     */
    public function rechargeFromMaster(Wallet $wallet, float $amount, User $masterUser, ?string $note = null): WalletTransaction
    {
        if (! $this->puedeGestionarBilleteras($masterUser)) {
            throw new AuthorizationException('Solo los usuarios con rol Master o Super Admin pueden administrar billeteras.');
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['El monto de la recarga debe ser un valor mayor a cero.'],
            ]);
        }

        if (is_null($wallet->user_id)) {
            throw ValidationException::withMessages([
                'wallet' => ['La billetera indicada no está asociada a un cajero.'],
            ]);
        }

        return DB::transaction(function () use ($wallet, $amount, $masterUser, $note): WalletTransaction {
            $walletActual = Wallet::query()
                ->where('id', $wallet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($walletActual->status === 'frozen') {
                throw new \RuntimeException('La billetera se encuentra congelada y no admite movimientos.');
            }

            $balanceBefore = (float) $walletActual->balance;
            $balanceAfter = round($balanceBefore + $amount, self::SCALE);

            $walletActual->update(['balance' => $balanceAfter]);

            return WalletTransaction::create([
                'wallet_id' => $walletActual->id,
                'type' => 'credit',
                'category' => 'master_recharge',
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => User::class,
                'reference_id' => $masterUser->id,
                'description' => $this->generarDescripcionRecarga($note),
                'created_by' => $masterUser->id,
            ]);
        });
    }

    /**
     * Calcula la comisión de un cajero sobre una venta completada y la acredita
     * en su billetera. Es idempotente: si la comisión ya fue acreditada para la
     * venta (reference_type + reference_id + category), no se recalcula.
     */
    public function calculateAndCreditCommission(Venta $sale): ?WalletTransaction
    {
        if (! $this->esVentaPagada($sale)) {
            return null;
        }

        if ($this->comisionYaConcedida($sale)) {
            return null;
        }

        $regla = $this->resolverReglaComision($sale);

        if ($regla === null) {
            return null;
        }

        $montoVenta = (float) $sale->total;

        if ($montoVenta < (float) $regla->min_sale_amount) {
            return null;
        }

        $montoComision = $this->calcularMontoComision($regla, $montoVenta);

        if ($montoComision <= 0) {
            return null;
        }

        return DB::transaction(function () use ($montoComision, $sale): WalletTransaction {
            $walletActual = $this->walletDelCajero($sale);

            if ($walletActual->status === 'frozen') {
                throw new \RuntimeException('La billetera del cajero se encuentra congelada y no admite comisiones.');
            }

            $balanceBefore = (float) $walletActual->balance;
            $balanceAfter = round($balanceBefore + $montoComision, self::SCALE);

            $walletActual->update(['balance' => $balanceAfter]);

            return WalletTransaction::create([
                'wallet_id' => $walletActual->id,
                'type' => 'credit',
                'category' => 'sale_commission',
                'amount' => $montoComision,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $sale::class,
                'reference_id' => $sale->id,
                'description' => 'Comisión por venta '.$sale->numero,
                'created_by' => $sale->user_id,
            ]);
        });
    }

    /**
     * Reversa la comisión acreditada cuando la venta es anulada o reembolsada.
     * Emite un débito (categoría refund) con el mismo monto revertido.
     */
    public function reverseCommission(Venta $sale): ?WalletTransaction
    {
        $transaction = WalletTransaction::query()
            ->where('reference_type', $sale::class)
            ->where('reference_id', $sale->id)
            ->where('category', 'sale_commission')
            ->where('type', 'credit')
            ->latest()
            ->first();

        if ($transaction === null) {
            // No existe comisión previa acreditada para esta venta.
            return null;
        }

        if ($this->comisionYaRevertida($sale)) {
            // Idempotencia: la comisión ya fue revertida.
            return $transaction;
        }

        return DB::transaction(function () use ($sale, $transaction): WalletTransaction {
            $wallet = Wallet::query()
                ->where('id', $transaction->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $monto = (float) $transaction->amount;

            if ((float) $wallet->balance < $monto && ! (bool) $wallet->allow_overdraft) {
                throw new \RuntimeException('Saldo insuficiente para revertir la comisión de la venta.');
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = round($balanceBefore - $monto, self::SCALE);

            $wallet->update(['balance' => $balanceAfter]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'type' => 'debit',
                'category' => 'refund',
                'amount' => $monto,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $sale::class,
                'reference_id' => $sale->id,
                'description' => 'Reversa de comisión por venta '.$sale->numero,
                'created_by' => $sale->user_id,
            ]);
        });
    }

    /**
     * Obtiene (o crea) la billetera de un usuario/cajero.
     */
    public function walletFor(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'currency' => 'CLP',
                'status' => 'active',
                'allow_overdraft' => false,
            ],
        );
    }

    /**
     * Valida si el usuario puede administrar billeteras (Master / Super Admin).
     */
    public function puedeGestionarBilleteras(User $user): bool
    {
        return $user->hasAnyRole(['Master', 'Super Admin']);
    }

    /**
     * Verifica si una venta fue pagada y obtiene el monto actual de comisión.
     */
    public function esVentaPagada(Venta $sale): bool
    {
        $estado = strtolower((string) $sale->estado);

        return in_array($estado, ['pagada', 'completada', 'pagado', 'paid'], true);
    }

    /**
     * Prioridad: regla específica del cajero > regla general del comercio.
     */
    public function resolverReglaComision(Venta $sale): ?CommissionRule
    {
        $tenantId = (int) ($sale->owner_id ?? 0);

        if ($tenantId === 0) {
            return null;
        }

        $reglaCajero = CommissionRule::query()
            ->where('is_active', true)
            ->where('user_id', $sale->user_id)
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();

        if ($reglaCajero !== null) {
            return $reglaCajero;
        }

        return CommissionRule::query()
            ->where('is_active', true)
            ->whereNull('user_id')
            ->where('tenant_id', $tenantId)
            ->latest('id')
            ->first();
    }

    /**
     * Calcula el monto de la comisión a partir del tipo (porcentaje o monto fijo).
     */
    public function calcularMontoComision(CommissionRule $regla, float $montoVenta): float
    {
        if ($regla->commission_type === 'fixed') {
            return round((float) $regla->commission_value, self::SCALE);
        }

        return round($montoVenta * ((float) $regla->commission_value / 100), self::SCALE);
    }

    /**
     * Historial de transacciones de billetera con filtros opcionales.
     */
    public function getTransacciones(?int $walletId = null, ?string $type = null, ?string $category = null, ?int $cajeroId = null, ?string $desde = null, ?string $hasta = null, ?int $ownerId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = WalletTransaction::query()
            ->with('wallet.user')
            ->when($walletId, fn (Builder $q) => $q->where('wallet_id', $walletId))
            ->when($type, fn (Builder $q) => $q->where('type', $type))
            ->when($category, fn (Builder $q) => $q->where('category', $category))
            ->when($cajeroId, fn (Builder $q) => $q->whereHas('wallet', fn (Builder $wq) => $wq->where('user_id', $cajeroId)))
            ->when($ownerId, fn (Builder $q) => $q->whereHas('wallet.user', fn (Builder $uq) => $uq->where('owner_id', $ownerId)))
            ->when($desde && strtotime($desde) !== false, fn (Builder $q) => $q->where('created_at', '>=', Carbon::parse($desde)->startOfDay()))
            ->when($hasta && strtotime($hasta) !== false, fn (Builder $q) => $q->where('created_at', '<=', Carbon::parse($hasta)->endOfDay()));

        return $query->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(fn (WalletTransaction $transaction): array => $this->transformTransaction($transaction))
            ->withQueryString();
    }

    /**
     * Comisiones acumuladas (acreditadas) para un cajero en el mes indicado.
     */
    public function comisionesDelCajero(User $cajero, ?string $fecha = null): float
    {
        $fechaCorte = $fecha ? Carbon::parse($fecha) : Carbon::now();
        $mesInicio = $fechaCorte->copy()->startOfMonth();
        $mesFin = $fechaCorte->copy()->endOfMonth();

        return (float) WalletTransaction::query()
            ->whereHas('wallet', fn (Builder $q) => $q->where('user_id', $cajero->id))
            ->where('type', 'credit')
            ->where('category', 'sale_commission')
            ->whereBetween('created_at', [$mesInicio, $mesFin])
            ->sum('amount');
    }

    /**
     * Total de comisiones generadas (acreditadas) para una billetera.
     */
    public function totalComisionesDelWallet(Wallet $wallet): float
    {
        return (float) WalletTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('type', 'credit')
            ->where('category', 'sale_commission')
            ->sum('amount');
    }

    /**
     * Normaliza un movimiento para la serialización de Inertia.
     *
     * Se listan explícitamente los campos para no exponer atributos sensibles
     * del usuario dueño de la billetera (password, remember_token, etc.).
     *
     * @return array<string, mixed>
     */
    private function transformTransaction(WalletTransaction $transaction): array
    {
        $wallet = $transaction->wallet;
        $user = $wallet?->user;

        return [
            'id' => $transaction->id,
            'wallet_id' => $transaction->wallet_id,
            'type' => (string) $transaction->type,
            'category' => (string) $transaction->category,
            'amount' => (float) $transaction->amount,
            'balance_before' => (float) $transaction->balance_before,
            'balance_after' => (float) $transaction->balance_after,
            'reference_type' => $transaction->reference_type,
            'reference_id' => $transaction->reference_id,
            'description' => $transaction->description,
            'created_at' => $transaction->created_at?->toIso8601String(),
            'wallet' => $wallet !== null ? [
                'id' => $wallet->id,
                'user' => $user !== null ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
            ] : null,
        ];
    }

    /**
     * Genera la descripción legible de una recarga.
     */
    private function generarDescripcionRecarga(?string $note): string
    {
        $texto = trim((string) $note);

        if ($texto === '') {
            return 'Recarga desde Master';
        }

        return $texto;
    }

    private function comisionYaConcedida(Venta $sale): bool
    {
        return WalletTransaction::query()
            ->where('reference_type', $sale::class)
            ->where('reference_id', $sale->id)
            ->where('category', 'sale_commission')
            ->where('type', 'credit')
            ->exists();
    }

    private function comisionYaRevertida(Venta $sale): bool
    {
        return WalletTransaction::query()
            ->where('reference_type', $sale::class)
            ->where('reference_id', $sale->id)
            ->where('category', 'refund')
            ->where('type', 'debit')
            ->exists();
    }

    private function walletDelCajero(Venta $sale): Wallet
    {
        $userId = (int) $sale->user_id;

        $wallet = Wallet::query()->where('user_id', $userId)->lockForUpdate()->first();

        if ($wallet !== null) {
            return $wallet;
        }

        return Wallet::create([
            'user_id' => $userId,
            'balance' => 0,
            'currency' => 'CLP',
            'status' => 'active',
            'allow_overdraft' => false,
        ]);
    }
}
