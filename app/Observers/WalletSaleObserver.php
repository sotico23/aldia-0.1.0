<?php

namespace App\Observers;

use App\Events\SaleCancelled;
use App\Events\SaleCompleted;
use App\Events\SaleRefunded;
use App\Models\Venta;

class WalletSaleObserver
{
    /**
     * Estados de venta que consideramos como "pagadas" (cobradas).
     */
    private const ESTADOS_PAGADOS = ['pagada', 'completada', 'pagado', 'paid'];

    /**
     * Estados que representan una venta anulada o cancelada.
     */
    private const ESTADOS_CANCELADAS = ['cancelada', 'anulada'];

    /**
     * Estados que representan una venta reembolsada.
     */
    private const ESTADOS_REEMBOLSADAS = ['reembolsada', 'reembolsado', 'refunded'];

    /**
     * Handle the Venta "created" event.
     *
     * El POS crea la venta directamente en estado "pagada", por lo que la
     * comisión debe acreditarse al momento de la creación.
     */
    public function created(Venta $venta): void
    {
        if ($this->esPagada((string) $venta->estado)) {
            SaleCompleted::dispatch($venta);
        }
    }

    /**
     * Handle the Venta "updated" event.
     */
    public function updated(Venta $venta): void
    {
        $estadoAnterior = (string) $venta->getOriginal('estado');
        $estadoNuevo = (string) $venta->estado;

        if ($estadoAnterior === $estadoNuevo) {
            return;
        }

        if ($this->esPagada($estadoNuevo) && ! $this->esPagada($estadoAnterior)) {
            SaleCompleted::dispatch($venta);

            return;
        }

        if ($this->esReembolsada($estadoNuevo) && $this->esPagada($estadoAnterior)) {
            SaleRefunded::dispatch($venta);

            return;
        }

        if ($this->esCancelada($estadoNuevo) && $this->esPagada($estadoAnterior)) {
            SaleCancelled::dispatch($venta);
        }
    }

    /**
     * Handle the Venta "deleted" event.
     */
    public function deleted(Venta $venta): void
    {
        //
    }

    /**
     * Handle the Venta "restored" event.
     */
    public function restored(Venta $venta): void
    {
        //
    }

    /**
     * Handle the Venta "force deleted" event.
     */
    public function forceDeleted(Venta $venta): void
    {
        //
    }

    private function esPagada(string $estado): bool
    {
        return in_array(strtolower($estado), self::ESTADOS_PAGADOS, true);
    }

    private function esCancelada(string $estado): bool
    {
        return in_array(strtolower($estado), self::ESTADOS_CANCELADAS, true);
    }

    private function esReembolsada(string $estado): bool
    {
        return in_array(strtolower($estado), self::ESTADOS_REEMBOLSADAS, true);
    }
}
