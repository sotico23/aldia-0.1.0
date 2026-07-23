<?php

namespace App\Livewire\Dashboard;

use App\Models\Cliente;
use Illuminate\Support\Facades\Auth;

class KpiClientes extends BaseWidget
{
    public int $totalClientes = 0;

    public function permission(): string|array
    {
        return 'comercial.clientes.viewAny';
    }

    public function loadData(): void
    {
        $ownerId = Auth::user()->getOwnerId();
        $this->totalClientes = Cliente::where('owner_id', $ownerId)->count();
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard.kpi-clientes');
    }
}
