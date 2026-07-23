<?php

namespace App\Livewire\Dashboard;

use App\Models\Factura;
use Illuminate\Support\Facades\Auth;

class KpiCuentasPorCobrar extends BaseWidget
{
    public int $cuentasPorCobrar = 0;

    public function permission(): string|array
    {
        return 'finanzas.facturacion.viewAny';
    }

    public function loadData(): void
    {
        $ownerId = Auth::user()->getOwnerId();

        $this->cuentasPorCobrar = (int) Factura::where('estado', 'pendiente')
            ->where('owner_id', $ownerId)
            ->sum('total');
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard.kpi-cuentas-por-cobrar');
    }
}
