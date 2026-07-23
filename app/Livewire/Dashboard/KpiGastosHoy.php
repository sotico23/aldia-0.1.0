<?php

namespace App\Livewire\Dashboard;

use App\Models\Compra;
use Illuminate\Support\Facades\Auth;

class KpiGastosHoy extends BaseWidget
{
    public int $gastosHoy = 0;

    public function permission(): string|array
    {
        return 'inventario.compras.viewAny';
    }

    public function loadData(): void
    {
        $ownerId = Auth::user()->getOwnerId();
        $hoy = now()->toDateString();

        $this->gastosHoy = (int) Compra::whereDate('fecha', $hoy)
            ->where('owner_id', $ownerId)
            ->sum('total');
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard.kpi-gastos-hoy');
    }
}
