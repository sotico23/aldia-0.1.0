<?php

namespace App\Listeners;

use App\Events\SaleCancelled;
use App\Events\SaleCompleted;
use App\Events\SaleRefunded;
use App\Services\WalletService;

class WalletSaleListener
{
    /**
     * Crédito automático de la comisión del cajero cuando la venta se cobra.
     */
    public function handleSaleCompleted(SaleCompleted $event): void
    {
        app(WalletService::class)->calculateAndCreditCommission($event->venta);
    }

    /**
     * Reversa la comisión acreditada cuando la venta es anulada o reembolsada.
     */
    public function handleSaleCancelled(SaleCancelled $event): void
    {
        app(WalletService::class)->reverseCommission($event->venta);
    }

    public function handleSaleRefunded(SaleRefunded $event): void
    {
        app(WalletService::class)->reverseCommission($event->venta);
    }
}
