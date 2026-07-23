<?php

namespace App\Livewire\Dashboard;

use App\Models\Venta;
use Illuminate\Support\Facades\Auth;

class KpiVentasPeriodo extends BaseWidget
{
    public int $ventasPeriodo = 0;

    public function permission(): string|array
    {
        return 'ventas.ventas.viewAny';
    }

    public function loadData(): void
    {
        $ownerId = Auth::user()->getOwnerId();
        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();

        $this->ventasPeriodo = (int) Venta::where('estado', 'pagada')
            ->whereBetween('fecha', [$inicioMes, $hoy])
            ->where('owner_id', $ownerId)
            ->sum('total');
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard.kpi-ventas-periodo');
    }
}
