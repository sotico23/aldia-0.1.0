<?php

namespace App\Livewire\Dashboard;

use App\Models\Venta;
use Illuminate\Support\Facades\Auth;

class KpiVentasHoy extends BaseWidget
{
    public int $ingresosHoy = 0;

    public function permission(): string|array
    {
        return 'ventas.ventas.viewAny';
    }

    public function loadData(): void
    {
        $ownerId = Auth::user()->getOwnerId();
        $hoy = now()->toDateString();

        $this->ingresosHoy = (int) Venta::where('estado', 'pagada')
            ->whereDate('fecha', $hoy)
            ->where('owner_id', $ownerId)
            ->sum('total');
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard.kpi-ventas-hoy');
    }
}
