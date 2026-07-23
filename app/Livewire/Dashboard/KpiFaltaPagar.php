<?php

namespace App\Livewire\Dashboard;

use App\Models\Compra;
use Illuminate\Support\Facades\Auth;

class KpiFaltaPagar extends BaseWidget
{
    public int $pendientePago = 0;

    public function permission(): string|array
    {
        return 'inventario.compras.viewAny';
    }

    public function loadData(): void
    {
        $ownerId = Auth::user()->getOwnerId();

        $this->pendientePago = (int) Compra::where('estado', 'pendiente')
            ->where('owner_id', $ownerId)
            ->sum('total');
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard.kpi-falta-pagar');
    }
}
