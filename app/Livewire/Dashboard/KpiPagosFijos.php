<?php

namespace App\Livewire\Dashboard;

use App\Models\Pago;
use Illuminate\Support\Facades\Auth;

class KpiPagosFijos extends BaseWidget
{
    public int $pagosFijosMes = 0;

    public function permission(): string|array
    {
        return 'finanzas.facturacion.viewAny';
    }

    public function loadData(): void
    {
        $ownerId = Auth::user()->getOwnerId();

        $this->pagosFijosMes = (int) Pago::whereMonth('fecha_pago', now()->month)
            ->whereYear('fecha_pago', now()->year)
            ->where('owner_id', $ownerId)
            ->sum('monto');
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard.kpi-pagos-fijos');
    }
}
