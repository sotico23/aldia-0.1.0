<?php

namespace App\Livewire\Dashboard;

use App\Models\Venta;
use Illuminate\Support\Facades\Auth;

class KpiComoVoy extends BaseWidget
{
    public string $comoVoy = '0%';

    public function permission(): string|array
    {
        return 'ventas.ventas.viewAny';
    }

    public function loadData(): void
    {
        $ownerId = Auth::user()->getOwnerId();
        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();
        $inicioMesAnterior = now()->subMonth()->startOfMonth()->toDateString();
        $finMesAnterior = now()->subMonth()->endOfMonth()->toDateString();

        $ventasMes = (int) Venta::where('estado', 'pagada')
            ->whereBetween('fecha', [$inicioMes, $hoy])
            ->where('owner_id', $ownerId)
            ->sum('total');

        $ventasMesAnterior = (int) Venta::where('estado', 'pagada')
            ->whereBetween('fecha', [$inicioMesAnterior, $finMesAnterior])
            ->where('owner_id', $ownerId)
            ->sum('total');

        if ($ventasMesAnterior > 0) {
            $diferencia = (($ventasMes - $ventasMesAnterior) / $ventasMesAnterior) * 100;
            $this->comoVoy = ($diferencia >= 0 ? '+' : '').number_format($diferencia, 1).'%';
        } else {
            $this->comoVoy = $ventasMes > 0 ? '+Nuevo' : '0%';
        }
    }

    public function render()
    {
        $this->loadData();

        return view('livewire.dashboard.kpi-como-voy');
    }
}
