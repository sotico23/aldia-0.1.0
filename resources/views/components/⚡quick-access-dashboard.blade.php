<?php

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\Proveedor;
use App\Models\Venta;
use Livewire\Component;

new class extends Component {
    public int $ingresosHoy = 0;

    public int $gastosHoy = 0;

    public int $pendientePago = 0;

    public string $comoVoy = '0%';

    public int $pagosFijosMes = 0;

    public int $totalProveedores = 0;

    public int $gastosMes = 0;

    public int $totalClientes = 0;

    public int $cuentasPorCobrar = 0;

    public int $ventasPeriodo = 0;

    public int $ventasPeriodoAnterior = 0;

    public function mount(): void
    {
        $ownerId = auth()->user()->getOwnerId();
        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();
        $inicioMesAnterior = now()->subMonth()->startOfMonth()->toDateString();
        $finMesAnterior = now()->subMonth()->endOfMonth()->toDateString();

        $this->ingresosHoy = (int) Venta::where('estado', 'pagada')
            ->whereDate('fecha', $hoy)
            ->where('owner_id', $ownerId)
            ->sum('total');

        $this->gastosHoy = (int) Compra::whereDate('fecha', $hoy)
            ->where('owner_id', $ownerId)
            ->sum('total');

        $this->pendientePago = (int) Compra::where('estado', 'pendiente')
            ->where('owner_id', $ownerId)
            ->sum('total');

        $ventasMes = (int) Venta::where('estado', 'pagada')
            ->whereBetween('fecha', [$inicioMes, $hoy])
            ->where('owner_id', $ownerId)
            ->sum('total');

        $ventasMesAnterior = (int) Venta::where('estado', 'pagada')
            ->whereBetween('fecha', [$inicioMesAnterior, $finMesAnterior])
            ->where('owner_id', $ownerId)
            ->sum('total');

        $this->ventasPeriodo = $ventasMes;
        $this->ventasPeriodoAnterior = $ventasMesAnterior;

        if ($ventasMesAnterior > 0) {
            $diferencia = (($ventasMes - $ventasMesAnterior) / $ventasMesAnterior) * 100;
            $this->comoVoy = ($diferencia >= 0 ? '+' : '').number_format($diferencia, 1).'%';
        } else {
            $this->comoVoy = $ventasMes > 0 ? '+Nuevo' : '0%';
        }

        $this->pagosFijosMes = (int) Pago::whereMonth('fecha_pago', now()->month)
            ->whereYear('fecha_pago', now()->year)
            ->where('owner_id', $ownerId)
            ->sum('monto');

        $this->totalProveedores = Proveedor::where('owner_id', $ownerId)->count();

        $this->gastosMes = (int) Compra::whereBetween('fecha', [$inicioMes, $hoy])
            ->where('owner_id', $ownerId)
            ->sum('total');

        $this->totalClientes = Cliente::where('owner_id', $ownerId)->count();

        $this->cuentasPorCobrar = (int) Factura::where('estado', 'pendiente')
            ->where('owner_id', $ownerId)
            ->sum('total');
    }
};
?>

<div class="rounded-2xl bg-[#1a1a1a] p-6 text-white shadow-xl">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-black tracking-tight">Panel Rápido</h2>
            <p class="text-sm text-gray-400">Resumen operativo del negocio</p>
        </div>
        <div class="flex items-center gap-2 rounded-full bg-white/5 px-4 py-2 text-sm">
            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
            {{ auth()->user()->name }}
        </div>
    </div>

    <div class="space-y-8">
        <!-- EL DÍA A DÍA -->
        <div>
            <div class="mb-4 flex items-center gap-2">
                <span class="rounded bg-white/10 px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase text-gray-400">Toda empresa</span>
                <span class="text-xs font-bold tracking-wider text-gray-500 uppercase">EL DÍA A DÍA</span>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-sii-card :href="route('pos.index')" accent="emerald" icon="arrow-trend-up" title="Lo que entró hoy" subtitle="Ventas del día" :value="'$'.number_format($ingresosHoy, 0, ',', '.')" />
                <x-sii-card :href="route('compras.index')" accent="rose" icon="arrow-trend-down" title="Lo que salió hoy" subtitle="Compras del día" :value="'$'.number_format($gastosHoy, 0, ',', '.')" />
                <x-sii-card :href="route('pagos.index')" accent="orange" icon="banknotes" title="Lo que me falta pagar" subtitle="Compras pendientes" :value="'$'.number_format($pendientePago, 0, ',', '.')" />
                <x-sii-card :href="route('dashboard')" accent="violet" icon="chart-bar" title="¿Cómo voy?" subtitle="Vs. mes anterior" :value="$comoVoy" :trend-up="str_starts_with($comoVoy, '+')" :trend-down="str_starts_with($comoVoy, '-')" />
            </div>
        </div>

        <!-- OPERACIÓN RECURRENTE -->
        <div>
            <div class="mb-4 flex items-center gap-2">
                <span class="rounded bg-amber-500/20 px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase text-amber-300">Caja / Operaciones</span>
                <span class="text-xs font-bold tracking-wider text-gray-500 uppercase">OPERACIÓN RECURRENTE</span>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-sii-card :href="route('pagos.index')" accent="amber" icon="calendar-days" title="Pagos fijos del mes" subtitle="Compromisos mensuales" :value="'$'.number_format($pagosFijosMes, 0, ',', '.')" />
                <x-sii-card :href="route('proveedors.index')" accent="cyan" icon="truck" title="Mis proveedores" subtitle="Total registrados" :value="(string)$totalProveedores" />
                <x-sii-card :href="route('compras.index')" accent="rose" icon="receipt-percent" title="Gastos del negocio" subtitle="Compras del mes" :value="'$'.number_format($gastosMes, 0, ',', '.')" />
            </div>
        </div>

        <!-- CON CLIENTES -->
        <div>
            <div class="mb-4 flex items-center gap-2">
                <span class="rounded bg-sky-500/20 px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase text-sky-300">CRM</span>
                <span class="text-xs font-bold tracking-wider text-gray-500 uppercase">CON CLIENTES</span>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-sii-card :href="route('clientes.index')" accent="sky" icon="users" title="Mis clientes" subtitle="Base de clientes activos" :value="(string)$totalClientes" />
                <x-sii-card :href="route('facturacion.index')" accent="violet" icon="credit-card" title="Lo que me deben" subtitle="Facturas pendientes" :value="'$'.number_format($cuentasPorCobrar, 0, ',', '.')" />
                <x-sii-card :href="route('ventas.index')" accent="emerald" icon="chart-line" title="Ventas del período" subtitle="Este mes" :value="'$'.number_format($ventasPeriodo, 0, ',', '.')" />
            </div>
        </div>
    </div>
</div>
