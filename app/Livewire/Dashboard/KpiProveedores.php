<?php

namespace App\Livewire\Dashboard;

use App\Models\Proveedor;
use Illuminate\Support\Facades\Auth;

class KpiProveedores extends BaseWidget
{
    public int $totalProveedores = 0;

    public function permission(): string|array
    {
        return 'inventario.proveedores.viewAny';
    }

    public function loadData(): void
    {
        $ownerId = Auth::user()->getOwnerId();
        $this->totalProveedores = Proveedor::where('owner_id', $ownerId)->count();
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard.kpi-proveedores');
    }
}
