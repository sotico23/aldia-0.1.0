<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\GastoProyecto;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutomationReportService
{
    public function generate(int $ownerId, array $selectedReports): array
    {
        $reports = [];

        if (empty($selectedReports)) {
            $selectedReports = [
                'resumen_ejecutivo', 'ventas', 'inventario', 'stock_bajo',
                'clientes_nuevos', 'clientes_inactivos', 'agenda_citas',
                'gastos', 'flujo_caja', 'ctas_cobrar', 'ctas_pagar',
            ];
        }

        $reportMethods = [
            'resumen_ejecutivo' => 'getResumenEjecutivo',
            'ventas' => 'getVentas',
            'inventario' => 'getInventario',
            'stock_bajo' => 'getStockBajo',
            'clientes_nuevos' => 'getClientesNuevos',
            'clientes_inactivos' => 'getClientesInactivos',
            'agenda_citas' => 'getAgendaCitas',
            'gastos' => 'getGastos',
            'flujo_caja' => 'getFlujoCaja',
            'ctas_cobrar' => 'getCtasCobrar',
            'ctas_pagar' => 'getCtasPagar',
        ];

        foreach ($selectedReports as $report) {
            if (isset($reportMethods[$report])) {
                try {
                    $reports[$report] = $this->{$reportMethods[$report]}($ownerId);
                } catch (\Throwable $e) {
                    Log::warning('Report generation failed', [
                        'report' => $report,
                        'owner_id' => $ownerId,
                        'error' => $e->getMessage(),
                    ]);
                    $reports[$report] = ['error' => true, 'message' => 'No disponible temporalmente'];
                }
            }
        }

        return $reports;
    }

    private function getResumenEjecutivo(int $ownerId): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();

        $ventasMes = Venta::where('owner_id', $ownerId)
            ->where('created_at', '>=', $monthStart)
            ->whereIn('estado', ['pagada', 'completada'])
            ->sum('total');

        $totalClientes = Cliente::where('owner_id', $ownerId)->count();
        $totalProductos = Producto::where('owner_id', $ownerId)->count();

        $gastosMes = GastoProyecto::where('owner_id', $ownerId)
            ->where('created_at', '>=', $monthStart)
            ->sum('monto') ?? 0;

        return [
            'periodo' => $now->isoFormat('MMMM YYYY'),
            'ventas_mes' => $ventasMes,
            'total_clientes' => $totalClientes,
            'total_productos' => $totalProductos,
            'gastos_mes' => $gastosMes,
            'margen' => $ventasMes - $gastosMes,
        ];
    }

    private function getVentas(int $ownerId): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $last7Days = $now->copy()->subDays(7);

        $ventasMes = Venta::where('owner_id', $ownerId)
            ->where('created_at', '>=', $monthStart)
            ->whereIn('estado', ['pagada', 'completada']);

        $totalVentasMes = $ventasMes->count();
        $totalMontoMes = $ventasMes->sum('total');

        $ventas7d = Venta::where('owner_id', $ownerId)
            ->where('created_at', '>=', $last7Days)
            ->whereIn('estado', ['pagada', 'completada'])
            ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total, SUM(total) as monto')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('MIN(created_at)')
            ->get();

        return [
            'total_ventas_mes' => $totalVentasMes,
            'total_monto_mes' => $totalMontoMes,
            'promedio_ticket' => $totalVentasMes > 0 ? $totalMontoMes / $totalVentasMes : 0,
            'ventas_7d' => $ventas7d,
        ];
    }

    private function getInventario(int $ownerId): array
    {
        $totalProductos = Producto::where('owner_id', $ownerId)->where('activo', true)->count();
        $totalStock = Inventario::where('owner_id', $ownerId)->sum('cantidad');

        $valorInventario = Inventario::where('owner_id', $ownerId)
            ->join('productos', 'inventarios.producto_id', '=', 'productos.id')
            ->selectRaw('SUM(inventarios.cantidad * productos.precio_venta) as valor')
            ->value('valor') ?? 0;

        return [
            'total_productos' => $totalProductos,
            'total_unidades' => $totalStock,
            'valor_inventario' => $valorInventario,
        ];
    }

    private function getStockBajo(int $ownerId): array
    {
        $productos = Inventario::where('owner_id', $ownerId)
            ->whereColumn('cantidad', '<=', 'cantidad_minima')
            ->where('cantidad_minima', '>', 0)
            ->with('producto')
            ->get();

        return [
            'total' => $productos->count(),
            'productos' => $productos->map(fn ($inv) => [
                'nombre' => $inv->producto?->nombre ?? 'Sin nombre',
                'stock' => $inv->cantidad,
                'minimo' => $inv->cantidad_minima,
            ]),
        ];
    }

    private function getClientesNuevos(int $ownerId): array
    {
        return [
            'total' => Cliente::where('owner_id', $ownerId)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count(),
            'periodo' => 'últimos 30 días',
        ];
    }

    private function getClientesInactivos(int $ownerId): array
    {
        $fecha = Carbon::now()->subDays(90);

        $clientes = Cliente::where('owner_id', $ownerId)
            ->whereDoesntHave('ventas', fn ($q) => $q->where('created_at', '>=', $fecha))
            ->get();

        return [
            'total' => $clientes->count(),
            'periodo_referencia' => 'sin compras en últimos 90 días',
        ];
    }

    private function getAgendaCitas(int $ownerId): array
    {
        try {
            return [
                'citas_hoy' => Appointment::where('owner_id', $ownerId)
                    ->whereDate('start_time', Carbon::today())->count(),
                'citas_proximos_7d' => Appointment::where('owner_id', $ownerId)
                    ->whereBetween('start_time', [Carbon::today(), Carbon::today()->addDays(7)])->count(),
            ];
        } catch (\Exception $e) {
            return ['citas_hoy' => 0, 'citas_proximos_7d' => 0, 'error' => 'Módulo de citas no disponible'];
        }
    }

    private function getGastos(int $ownerId): array
    {
        $monthStart = Carbon::now()->startOfMonth();

        return [
            'total_mes' => GastoProyecto::where('owner_id', $ownerId)
                ->where('created_at', '>=', $monthStart)->sum('monto') ?? 0,
            'por_categoria' => GastoProyecto::where('owner_id', $ownerId)
                ->where('created_at', '>=', $monthStart)
                ->selectRaw('categoria, SUM(monto) as total')
                ->groupBy('categoria')
                ->get(),
        ];
    }

    private function getFlujoCaja(int $ownerId): array
    {
        $monthStart = Carbon::now()->startOfMonth();

        $ingresos = Venta::where('owner_id', $ownerId)
            ->where('created_at', '>=', $monthStart)
            ->whereIn('estado', ['pagada', 'completada'])
            ->sum('total');

        $egresos = GastoProyecto::where('owner_id', $ownerId)
            ->where('created_at', '>=', $monthStart)
            ->sum('monto') ?? 0;

        return [
            'ingresos' => $ingresos,
            'egresos' => $egresos,
            'saldo' => $ingresos - $egresos,
            'periodo' => Carbon::now()->isoFormat('MMMM YYYY'),
        ];
    }

    private function getCtasCobrar(int $ownerId): array
    {
        try {
            $total = Venta::where('owner_id', $ownerId)->where('estado', 'pendiente')->sum('total');
            $count = Venta::where('owner_id', $ownerId)->where('estado', 'pendiente')->count();

            return ['total' => $total, 'cantidad' => $count];
        } catch (\Exception $e) {
            return ['total' => 0, 'cantidad' => 0, 'error' => 'No disponible'];
        }
    }

    private function getCtasPagar(int $ownerId): array
    {
        try {
            $total = Compra::where('owner_id', $ownerId)->where('estado', 'pendiente')->sum('total') ?? 0;
            $count = Compra::where('owner_id', $ownerId)->where('estado', 'pendiente')->count();

            return ['total' => $total, 'cantidad' => $count];
        } catch (\Exception $e) {
            return ['total' => 0, 'cantidad' => 0, 'error' => 'No disponible'];
        }
    }
}
