<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Cotizacion;
use App\Models\Inventario;
use App\Models\Oportunidad;
use App\Models\Producto;
use App\Models\Prospecto;
use App\Models\Ticket;
use App\Models\Venta;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReporteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [];
    }

    public function index(): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $mesActual = now()->month;
        $anioActual = now()->year;
        $mesAnterior = now()->subMonth()->month;
        $anioAnterior = now()->subMonth()->year;

        // ── KPIs globales ────────────────────────────────────────────
        $ventasMes = Venta::where('owner_id', $ownerId)->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual)->sum('total');
        $ventasMesAnt = Venta::where('owner_id', $ownerId)->whereMonth('fecha', $mesAnterior)->whereYear('fecha', $anioAnterior)->sum('total');
        $ventasCamb = $ventasMesAnt > 0 ? round((($ventasMes - $ventasMesAnt) / $ventasMesAnt) * 100, 1) : 0;

        $clientesTotal = Cliente::where('owner_id', $ownerId)->where('activo', true)->count();
        $clientesMes = Cliente::where('owner_id', $ownerId)->where('activo', true)->whereMonth('created_at', $mesActual)->whereYear('created_at', $anioActual)->count();

        $comprasMes = Compra::where('owner_id', $ownerId)->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual)->sum('total') ?? 0;
        $comprasMesAnt = Compra::where('owner_id', $ownerId)->whereMonth('fecha', $mesAnterior)->whereYear('fecha', $anioAnterior)->sum('total') ?? 0;

        $productosTotal = Producto::where('owner_id', $ownerId)->where('activo', true)->count();
        $stockBajo = Inventario::where('owner_id', $ownerId)->whereColumn('cantidad', '<=', 'cantidad_minima')->count();

        $cotizacionesMes = Cotizacion::where('owner_id', $ownerId)->whereMonth('fecha', $mesActual)->whereYear('fecha', $anioActual)->count();
        $cotizacionesTotal = Cotizacion::where('owner_id', $ownerId)->count();

        $prospectosMes = Prospecto::where('owner_id', $ownerId)->whereMonth('created_at', $mesActual)->whereYear('created_at', $anioActual)->count();
        $oportunidades = Oportunidad::where('owner_id', $ownerId)->where('etapa', '!=', 'closed_lost')->count();

        $ticketsAbiertos = Ticket::where('owner_id', $ownerId)->whereIn('estado', ['abierto', 'en_proceso'])->count();

        // ── Ventas por mes (últimos 12 meses) ────────────────────────
        $ventasPorMes = Venta::where('owner_id', $ownerId)
            ->selectRaw("
                DATE_FORMAT(fecha, '%Y-%m') as mes,
                SUM(total) as total,
                COUNT(*) as cantidad
            ")
            ->where('fecha', '>=', now()->subMonths(11)->startOfMonth())
            ->groupByRaw("DATE_FORMAT(fecha, '%Y-%m')")
            ->orderBy('mes')
            ->get()
            ->map(fn ($r) => [
                'mes' => $r->mes,
                'total' => round((float) $r->total, 2),
                'cantidad' => (int) $r->cantidad,
            ]);

        // ── Top 5 productos más vendidos ─────────────────────────────
        $topProductos = DB::table('detalle_ventas')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->where('productos.owner_id', $ownerId)
            ->selectRaw('productos.nombre, SUM(detalle_ventas.cantidad) as unidades, SUM(detalle_ventas.subtotal) as ingreso')
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('unidades')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'nombre' => $r->nombre,
                'unidades' => (int) $r->unidades,
                'ingreso' => round((float) $r->ingreso, 2),
            ]);

        // ── Top 5 clientes por ingresos ──────────────────────────────
        $topClientes = Venta::where('ventas.owner_id', $ownerId)
            ->join('clientes', 'ventas.cliente_id', '=', 'clientes.id')
            ->selectRaw('clientes.nombre, SUM(ventas.total) as total, COUNT(*) as pedidos')
            ->groupBy('clientes.id', 'clientes.nombre')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'nombre' => $r->nombre,
                'total' => round((float) $r->total, 2),
                'pedidos' => (int) $r->pedidos,
            ]);

        // ── Ventas por estado ─────────────────────────────────────────
        $ventasPorEstado = Venta::where('owner_id', $ownerId)
            ->selectRaw('estado, COUNT(*) as cantidad, SUM(total) as total')
            ->groupBy('estado')
            ->get()
            ->map(fn ($r) => [
                'estado' => $r->estado,
                'cantidad' => (int) $r->cantidad,
                'total' => round((float) $r->total, 2),
            ]);

        // ── Inventario: productos con stock bajo ──────────────────────
        $stockBajoDetalle = Inventario::with('producto')
            ->where('owner_id', $ownerId)
            ->whereColumn('cantidad', '<=', 'cantidad_minima')
            ->orderBy('cantidad')
            ->limit(10)
            ->get()
            ->map(fn ($i) => [
                'producto' => $i->producto->nombre ?? '-',
                'cantidad' => (int) $i->cantidad,
                'minimo' => (int) $i->cantidad_minima,
                'deficit' => max(0, (int) $i->cantidad_minima - (int) $i->cantidad),
            ]);

        // ── Pipeline CRM ──────────────────────────────────────────────
        $pipeline = Oportunidad::where('owner_id', $ownerId)
            ->selectRaw('etapa, COUNT(*) as cantidad, SUM(valor) as valor')
            ->groupBy('etapa')
            ->get()
            ->map(fn ($r) => [
                'etapa' => $r->etapa,
                'cantidad' => (int) $r->cantidad,
                'valor' => round((float) $r->valor, 2),
            ]);

        return Inertia::render('Backend/Reportes/Index', [
            'kpis' => [
                'ventas_mes' => round((float) $ventasMes, 2),
                'ventas_cambio' => $ventasCamb,
                'clientes_total' => $clientesTotal,
                'clientes_nuevos' => $clientesMes,
                'compras_mes' => round((float) $comprasMes, 2),
                'productos_total' => $productosTotal,
                'stock_bajo' => $stockBajo,
                'cotizaciones_mes' => $cotizacionesMes,
                'cotizaciones_total' => $cotizacionesTotal,
                'prospectos_mes' => $prospectosMes,
                'oportunidades' => $oportunidades,
                'tickets_abiertos' => $ticketsAbiertos,
            ],
            'ventas_por_mes' => $ventasPorMes,
            'top_productos' => $topProductos,
            'top_clientes' => $topClientes,
            'ventas_por_estado' => $ventasPorEstado,
            'stock_bajo' => $stockBajoDetalle,
            'pipeline' => $pipeline,
        ]);
    }
}
