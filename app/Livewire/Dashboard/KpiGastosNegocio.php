<?php

namespace App\Livewire\Dashboard;

use App\Models\Compra;
use Illuminate\Support\Facades\Auth;

class KpiGastosNegocio extends BaseWidget
{
    public int $gastosMes = 0;

    public function permission(): string|array
    {
        return 'inventario.compras.viewAny';
    }

    public function loadData(): void
    {
        $ownerId = Auth::user()->getOwnerId();
        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();

        $this->gastosMes = (int) Compra::whereBetween('fecha', [$inicioMes, $hoy])
            ->where('owner_id', $ownerId)
            ->sum('total');
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard.kpi-gastos-negocio');
    }
}
