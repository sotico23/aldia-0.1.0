<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\MoneyHelper;
use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Appointment;
use App\Models\Bom;
use App\Models\CafFolio;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\ConfiguracionSii;
use App\Models\ControlCalidad;
use App\Models\DashboardConfig;
use App\Models\DetalleVenta;
use App\Models\Factura;
use App\Models\Inventario;
use App\Models\InventoryMovement;
use App\Models\Mensaje;
use App\Models\OrdenProduccion;
use App\Models\PageView;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\UserDashboardWidget;
use App\Models\Venta;
use App\Scopes\OwnerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $ownerId = $user->getOwnerId();
        $anioActual = now()->year;
        $mesActual = now()->month;

        $esSuperAdmin = $user->hasRole('Super Admin') || $user->hasRole('Master');

        $mensajesSinLeer = Mensaje::where('receiver_id', $user->id)->where('leido', false)->count();

        // Build base queries scoped to owner (multitenant) or global for SuperAdmin
        if ($esSuperAdmin) {
            $baseVentas = Venta::withoutGlobalScope(OwnerScope::class)->where('estado', 'pagada');
            $baseCompras = Compra::withoutGlobalScope(OwnerScope::class);
            $baseProductos = Producto::withoutGlobalScope(OwnerScope::class);
        } else {
            $baseVentas = Venta::query()->where('estado', 'pagada');
            $baseCompras = Compra::query();
            $baseProductos = Producto::query();
        }

        $ventasStats = (clone $baseVentas)
            ->whereYear('fecha', $anioActual)
            ->whereMonth('fecha', $mesActual)
            ->selectRaw('SUM(total) as gross, SUM(subtotal) as net, SUM(iva) as iva_debito')
            ->first();

        if (! (optional($ventasStats)->gross)) {
            $ventasStats = (clone $baseVentas)
                ->whereYear('fecha', $anioActual)
                ->selectRaw('SUM(total) as gross, SUM(subtotal) as net, SUM(iva) as iva_debito')
                ->first();
        }
        $ventasStats = $ventasStats ?: (object) ['gross' => 0, 'net' => 0, 'iva_debito' => 0];

        $comprasStats = (clone $baseCompras)
            ->whereYear('fecha', $anioActual)
            ->whereMonth('fecha', $mesActual)
            ->selectRaw('SUM(total) as gross, SUM(subtotal) as net, SUM(iva) as iva_credito')
            ->first();

        if (! (optional($comprasStats)->gross)) {
            $comprasStats = (clone $baseCompras)
                ->whereYear('fecha', $anioActual)
                ->selectRaw('SUM(total) as gross, SUM(subtotal) as net, SUM(iva) as iva_credito')
                ->first();
        }
        $comprasStats = $comprasStats ?: (object) ['gross' => 0, 'net' => 0, 'iva_credito' => 0];

        $porCobrar = (clone $baseVentas)->where('estado', 'pendiente')->sum('total');
        $porPagar = (clone $baseCompras)->where('estado', 'pendiente')->sum('total');

        $baseStockCritico = (clone $baseProductos)
            ->whereRaw('stock_minimo >= (select COALESCE(sum(cantidad), 0) from inventarios where producto_id = productos.id)');

        $stockCritico = (clone $baseStockCritico)->count();
        $productosCriticos = (clone $baseStockCritico)
            ->select('id', 'nombre', 'codigo', 'stock_minimo')
            ->selectRaw('(select COALESCE(sum(cantidad), 0) from inventarios where producto_id = productos.id) as stock_actual')
            ->limit(5)
            ->get();

        $utilidadNeta = ((float) ($ventasStats->net ?? 0)) - ((float) ($comprasStats->net ?? 0));

        $stats = [
            (object) [
                'label' => 'Ventas (Año)',
                'value' => $this->formatCurrency($ventasStats->gross ?? 0),
                'subValue' => 'Neto: '.$this->formatCurrency($ventasStats->net ?? 0),
            ],
            (object) [
                'label' => 'IVA Estimado',
                'value' => $this->formatCurrency(max(0, ($ventasStats->iva_debito ?? 0) - ($comprasStats->iva_credito ?? 0))),
                'subValue' => 'Basado en año actual',
            ],
            (object) [
                'label' => 'Utilidad Neta',
                'value' => $this->formatCurrency($utilidadNeta),
                'subValue' => 'Ingresos - Egresos (YTD)',
            ],
            (object) [
                'label' => 'Cuentas x Cobrar',
                'value' => $this->formatCurrency($porCobrar),
                'subValue' => 'Facturas Pendientes',
            ],
            (object) [
                'label' => 'Cuentas x Pagar',
                'value' => $this->formatCurrency($porPagar),
                'subValue' => 'Compras Pendientes',
            ],
            (object) [
                'label' => 'Stock Crítico',
                'value' => $stockCritico,
                'subValue' => 'Productos a Reponer',
            ],
        ];

        $topProductosQuery = DetalleVenta::query()
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id');

        if (! $esSuperAdmin) {
            $topProductosQuery->where('productos.owner_id', $ownerId);
        }

        $topProductos = $topProductosQuery
            ->select('productos.nombre', DB::raw('SUM(detalle_ventas.cantidad) as total_cantidad'), DB::raw('SUM(detalle_ventas.subtotal) as total_venta'))
            ->groupBy('productos.id', 'productos.nombre')
            ->orderByDesc('total_cantidad')
            ->limit(5)
            ->get();

        $dateFormat = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%%Y-%%m', fecha)"
            : "DATE_FORMAT(fecha, '%Y-%m')";

        $ventasRaw = ($esSuperAdmin ? Venta::withoutGlobalScope(OwnerScope::class) : Venta::query())
            ->where('estado', 'pagada')
            ->whereYear('fecha', '>=', $anioActual - 1)
            ->select(DB::raw("{$dateFormat} as mes"), 'almacen_id')
            ->selectRaw('SUM(total) as total, SUM(subtotal) as subtotal')
            ->groupBy('mes', 'almacen_id')
            ->orderBy('mes')
            ->with('almacen:id,nombre')
            ->get();

        $almacenes = $ventasRaw
            ->whereNotNull('almacen')
            ->pluck('almacen.nombre', 'almacen_id')
            ->unique()
            ->map(fn ($nombre, $id) => ['key' => 'b'.$id, 'nombre' => $nombre])
            ->values();

        $ventasMensuales = $ventasRaw->groupBy('mes')->map(function ($items, $mes) {
            $row = ['mes' => $mes];
            $row['total'] = $items->sum('total');
            $row['subtotal'] = $items->sum('subtotal');
            foreach ($items as $item) {
                if ($item->almacen) {
                    $key = 'b'.$item->almacen_id;
                    $row[$key] = ($row[$key] ?? 0) + (float) $item->total;
                }
            }

            return $row;
        })->values();

        $siiConfig = ConfiguracionSii::where('owner_id', $ownerId)->first();
        $cafs = CafFolio::where('owner_id', $ownerId)->where('agotado', false)->get();
        $hasSiiToken = Cache::has('sii_token');

        $siiStats = [
            'ambiente' => $siiConfig->ambiente ?? config('sii.ambiente', 'certificacion'),
            'emisor' => $siiConfig ? [
                'rut' => $siiConfig->rut,
                'razon_social' => $siiConfig->razon_social,
            ] : null,
            'token_activo' => $hasSiiToken,
            'folios_disponibles' => $cafs->map(fn ($c) => [
                'tipo' => $c->tipo_documento,
                'restantes' => ($c->folio_hasta - $c->folio_siguiente) + 1,
            ]),
        ];

        $masterData = [];
        if ($esSuperAdmin) {
            $usuariosQuery = User::withoutGlobalScope(OwnerScope::class);

            $masterData['nuevosUsuarios7d'] = (clone $usuariosQuery)
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get();

            $masterData['nuevosUsuarios30d'] = (clone $usuariosQuery)
                ->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('DATE(created_at) as fecha, COUNT(*) as total')
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get();

            $masterData['referidosStats'] = [
                'total_referidos' => (clone $usuariosQuery)->whereNotNull('referred_by')->count(),
                'referidos_7d' => (clone $usuariosQuery)->whereNotNull('referred_by')->where('created_at', '>=', now()->subDays(7))->count(),
                'referidos_30d' => (clone $usuariosQuery)->whereNotNull('referred_by')->where('created_at', '>=', now()->subDays(30))->count(),
                'total_usuarios' => (clone $usuariosQuery)->count(),
            ];

            $masterData['topReferentes'] = (clone $usuariosQuery)
                ->has('referrals')
                ->withCount('referrals')
                ->orderBy('referrals_count', 'desc')
                ->limit(10)
                ->get(['id', 'name', 'email']);

            $masterData['ultimosReferidos'] = (clone $usuariosQuery)
                ->whereNotNull('referred_by')
                ->with('referrer:id,name,email')
                ->latest()
                ->limit(20)
                ->get();

            $masterData['paginasMasVistas7d'] = PageView::lastDays(7)
                ->selectRaw('url, route_name, COUNT(*) as visits')
                ->groupBy('url', 'route_name')
                ->orderByDesc('visits')
                ->limit(20)
                ->get();

            $masterData['paginasMasVistas30d'] = PageView::lastDays(30)
                ->selectRaw('url, route_name, COUNT(*) as visits')
                ->groupBy('url', 'route_name')
                ->orderByDesc('visits')
                ->limit(20)
                ->get();

            $masterData['vistasPorDia7d'] = PageView::lastDays(7)
                ->selectRaw('DATE(visited_at) as fecha, COUNT(*) as total')
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get();

            $masterData['vistasPorDia30d'] = PageView::lastDays(30)
                ->selectRaw('DATE(visited_at) as fecha, COUNT(*) as total')
                ->groupBy('fecha')
                ->orderBy('fecha')
                ->get();

            $masterData['usuariosActivos'] = DB::table('sessions')
                ->whereNotNull('user_id')
                ->where('last_activity', '>=', now()->subMinutes(15)->getTimestamp())
                ->distinct('user_id')
                ->count('user_id');
        }

        // --- Próximas Citas ---
        $proximasCitas = $user->can('citas.citas.viewAny')
            ? Appointment::with('client:id,name', 'provider:id,name', 'producto:id,nombre')
                ->where('start_time', '>=', now())
                ->orderBy('start_time')
                ->limit(5)
                ->get()
            : collect();

        // --- Citas del Mes (para widget calendario) ---
        $citasDelMes = $user->can('citas.citas.viewAny')
            ? Appointment::with('client:id,name', 'producto:id,nombre')
                ->whereBetween('start_time', [now()->startOfMonth(), now()->endOfMonth()])
                ->orderBy('start_time')
                ->get(['id', 'client_id', 'producto_id', 'start_time', 'end_time', 'status'])
            : collect();

        $widgetKpis = $this->computeWidgetKpis($user, $ownerId);

        // --- Inventario Data (RendimientoInventarioWidget) ---
        $baseInvProductos = $esSuperAdmin
            ? Producto::withoutGlobalScope(OwnerScope::class)
            : Producto::query();

        $totalProductos = (clone $baseInvProductos)->count();
        $productosActivos = (clone $baseInvProductos)->where('activo', true)->count();

        $baseInventarios = $esSuperAdmin
            ? Inventario::withoutGlobalScope(OwnerScope::class)
            : Inventario::query();

        $baseAlmacenes = $esSuperAdmin
            ? Almacen::withoutGlobalScope(OwnerScope::class)
            : Almacen::query();

        $almacenesActivos = (clone $baseAlmacenes)->where('activo', true)->count();

        $stockValue = (clone $baseInventarios)
            ->join('productos', 'inventarios.producto_id', '=', 'productos.id')
            ->sum(DB::raw('COALESCE(inventarios.cantidad, 0) * COALESCE(productos.precio_compra, 0)'));

        $criticalStockCount = (clone $baseInventarios)
            ->whereColumn('cantidad', '<=', 'cantidad_minima')
            ->count();

        $criticalStockItems = (clone $baseInventarios)
            ->whereColumn('cantidad', '<=', 'cantidad_minima')
            ->with('producto:id,nombre')
            ->limit(5)
            ->get()
            ->map(fn ($inv) => [
                'nombre' => $inv->producto?->nombre ?? 'Sin nombre',
                'stock' => (int) $inv->cantidad,
                'minimo' => (int) $inv->cantidad_minima,
            ]);

        $baseMovements = $esSuperAdmin
            ? InventoryMovement::withoutGlobalScope(OwnerScope::class)
            : InventoryMovement::query();

        $recentMovements = (clone $baseMovements)
            ->with(['product:id,nombre', 'sourceWarehouse:id,nombre', 'destinationWarehouse:id,nombre'])
            ->latest()
            ->limit(4)
            ->get()
            ->map(fn ($m) => [
                'tipo' => match ($m->type) {
                    'INGRESO' => 'entrada',
                    'EGRESO' => 'salida',
                    'TRASLADO' => 'traslado',
                    default => 'movimiento',
                },
                'producto' => $m->product?->nombre ?? 'Producto',
                'cantidad' => abs($m->quantity),
                'fecha' => $m->created_at->diffForHumans(),
                'almacen' => $m->type === 'TRASLADO'
                    ? ($m->sourceWarehouse?->nombre ?? '?').' → '.($m->destinationWarehouse?->nombre ?? '?')
                    : ($m->sourceWarehouse?->nombre ?? $m->destinationWarehouse?->nombre ?? '?'),
            ]);

        $inventarioData = [
            'totalProductos' => $totalProductos,
            'activos' => $productosActivos,
            'almacenes' => $almacenesActivos,
            'stockCritico' => $criticalStockCount,
            'valorInventario' => (int) $stockValue,
            'movimientosRecientes' => $recentMovements,
            'productosCriticos' => $criticalStockItems,
        ];

        // --- Produccion MRP Data (ProduccionMrpWidget) ---
        $baseOrdenes = $esSuperAdmin
            ? OrdenProduccion::withoutGlobalScope(OwnerScope::class)
            : OrdenProduccion::query();

        $ordenesPendientes = (clone $baseOrdenes)->where('estado', 'pendiente')->count();
        $ordenesEnProceso = (clone $baseOrdenes)->where('estado', 'en_proceso')->count();
        $ordenesCompletadas = (clone $baseOrdenes)->where('estado', 'completado')->count();
        $ordenesCanceladas = (clone $baseOrdenes)->where('estado', 'cancelado')->count();

        $totalTerminadas = $ordenesCompletadas + $ordenesCanceladas;
        $eficiencia = $totalTerminadas > 0 ? (int) round(($ordenesCompletadas / $totalTerminadas) * 100) : 100;

        $baseBoms = $esSuperAdmin
            ? Bom::withoutGlobalScope(OwnerScope::class)
            : Bom::query();

        $totalBomsActivas = (clone $baseBoms)->where('activo', true)->count();

        $baseCalidad = $esSuperAdmin
            ? ControlCalidad::withoutGlobalScope(OwnerScope::class)
            : ControlCalidad::query();

        $calidadPendientes = (clone $baseCalidad)->where('resultado', 'pendiente')->count();
        $calidadAprobados = (clone $baseCalidad)->where('resultado', 'aprobado')->count();
        $calidadRechazados = (clone $baseCalidad)->where('resultado', 'rechazado')->count();

        $proximasOrdenes = (clone $baseOrdenes)
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->with('producto:id,nombre')
            ->orderBy('fecha_inicio')
            ->limit(3)
            ->get()
            ->map(fn ($op) => [
                'id' => $op->numero,
                'producto' => $op->producto?->nombre ?? $op->producto ?? 'Producto',
                'cantidad' => (int) $op->cantidad,
                'fecha' => $op->fecha_inicio?->format('Y-m-d') ?? $op->created_at->format('Y-m-d'),
                'estado' => $op->estado,
                'progreso' => $op->progreso,
            ]);

        $produccionData = [
            'ordenes' => [
                'pendientes' => $ordenesPendientes,
                'enProceso' => $ordenesEnProceso,
                'completadas' => $ordenesCompletadas,
                'canceladas' => $ordenesCanceladas,
            ],
            'totalBoms' => $totalBomsActivas,
            'controlCalidad' => [
                'pendientes' => $calidadPendientes,
                'aprobados' => $calidadAprobados,
                'rechazados' => $calidadRechazados,
            ],
            'proximasOrdenes' => $proximasOrdenes,
            'eficiencia' => $eficiencia,
        ];

        // --- Today's Metrics (metrics_summary widget) ---
        $hoy = now()->toDateString();
        $inicioSemana = now()->startOfWeek()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();

        $tienePermisoCitas = $user->can('citas.citas.viewAny');

        $baseAppointments = $tienePermisoCitas
            ? ($esSuperAdmin
                ? Appointment::withoutGlobalScope(OwnerScope::class)
                : Appointment::query())
            : null;

        $ventasHoy = (clone $baseVentas)->whereDate('fecha', $hoy);
        $ingresosHoy = (int) (clone $ventasHoy)->sum('total');
        $serviciosHoy = (clone $ventasHoy)->count();

        $comprasHoy = (clone $baseCompras)->whereDate('fecha', $hoy);
        $egresosHoy = (int) (clone $comprasHoy)->sum('total');
        $pagosCountHoy = (clone $comprasHoy)->count();

        $comprasPendientes = (clone $baseCompras)
            ->where('estado', 'pendiente')
            ->with('proveedor:id,nombre')
            ->get();

        $pendingTotal = (int) (clone $comprasPendientes)->sum('total');
        $pendingFirst = $comprasPendientes->first();
        $pendingDetail = $pendingFirst
            ? ($pendingFirst->proveedor?->nombre ?? 'Proveedor').' · $'.number_format((int) $pendingFirst->total, 0, ',', '.')
            : 'Sin pendientes';

        $appointmentsToday = $tienePermisoCitas
            ? (clone $baseAppointments)
                ->whereDate('start_time', $hoy)
                ->with(['client:id,name', 'provider:id,name', 'producto:id,nombre,precio_venta,duracion'])
                ->orderBy('start_time')
                ->get()
            : collect();

        $citasHoy = $appointmentsToday->count();
        $citasPendientes = $appointmentsToday->whereIn('status', ['pendiente', 'confirmada'])->count();

        $metricsSummary = [
            ['id' => 'ingresos_dia', 'label' => 'Ingresos hoy', 'value' => $ingresosHoy, 'format' => 'currency', 'sub_label' => $serviciosHoy.' servicios', 'color_valor' => 'text-[#085041]', 'borde_izquierdo' => '#1D9E75', 'icon_name' => 'trending-up', 'bgIcon' => 'bg-[#1D9E75]/10'],
            ['id' => 'egresos_dia', 'label' => 'Egresos hoy', 'value' => $egresosHoy, 'format' => 'currency', 'sub_label' => $pagosCountHoy.' pagos', 'color_valor' => 'text-[#993C1D]', 'borde_izquierdo' => '#D85A30', 'icon_name' => 'trending-down', 'bgIcon' => 'bg-[#D85A30]/10'],
            ['id' => 'pagos_pendientes_total', 'label' => 'Pendiente pago', 'value' => $pendingTotal, 'format' => 'currency', 'sub_label' => $pendingDetail, 'color_valor' => 'text-[#854F0B]', 'borde_izquierdo' => '#BA7517', 'icon_name' => 'clock', 'bgIcon' => 'bg-[#BA7517]/10'],
            ['id' => 'citas_dia', 'label' => 'Citas hoy', 'value' => $citasHoy, 'format' => 'number', 'sub_label' => $citasPendientes.' en espera', 'color_valor' => 'text-[#7F77DD]', 'borde_izquierdo' => '#7F77DD', 'icon_name' => 'calendar-check', 'bgIcon' => 'bg-[#7F77DD]/10'],
        ];

        // --- Week appointments (for agenda week view) ---
        $appointmentsWeek = $tienePermisoCitas
            ? (clone $baseAppointments)
                ->whereBetween('start_time', [$inicioSemana, $hoy.' 23:59:59'])
                ->with(['client:id,name', 'provider:id,name', 'producto:id,nombre,precio_venta,duracion'])
                ->orderBy('start_time')
                ->get()
            : collect();

        $agendaWeekAppointments = $appointmentsWeek->map(fn ($a) => [
            'id' => 'CIT-'.str_pad((string) $a->id, 3, '0', STR_PAD_LEFT),
            'staff_id' => 'STF-'.str_pad((string) ($a->provider_id ?? 0), 3, '0', STR_PAD_LEFT),
            'cliente' => $a->client?->name ?? 'Cliente',
            'servicio' => $a->producto?->nombre ?? 'Servicio',
            'precio' => '$'.number_format((int) ($a->producto?->precio_venta ?? 0), 0, ',', '.'),
            'start' => $a->start_time->hour + ($a->start_time->minute / 60),
            'duration' => max(0.5, ($a->producto?->duracion ?? 30) / 60),
        ]);

        // --- Staff/Providers for agenda ---
        $providerIds = $tienePermisoCitas
            ? $appointmentsToday->pluck('provider_id')->unique()->filter()
                ->merge($appointmentsWeek->pluck('provider_id')->unique()->filter())
                ->unique()
            : collect();
        $providerColors = [
            ['color' => '#B5D4F4', 'texto' => '#042C53', 'border' => '#185FA5'],
            ['color' => '#CECBF6', 'texto' => '#26215C', 'border' => '#534AB7'],
            ['color' => '#F4C0D1', 'texto' => '#4B1528', 'border' => '#D4537E'],
            ['color' => '#C1E5C0', 'texto' => '#1A4A1A', 'border' => '#3E8E41'],
            ['color' => '#FCE4B8', 'texto' => '#5C3D00', 'border' => '#B8860B'],
            ['color' => '#D4C5F9', 'texto' => '#2D1B69', 'border' => '#6A3DE6'],
        ];

        $staff = $tienePermisoCitas
            ? User::whereIn('id', $providerIds)
                ->get(['id', 'name'])
                ->values()
                ->map(fn ($u, $i) => [
                    'id' => 'STF-'.str_pad((string) $u->id, 3, '0', STR_PAD_LEFT),
                    'nombre' => explode(' ', $u->name)[0],
                    ...($providerColors[$i % count($providerColors)] ?? $providerColors[0]),
                ])
            : collect();

        // --- Agenda appointments ---
        $agendaAppointments = $appointmentsToday->map(fn ($a) => [
            'id' => 'CIT-'.str_pad((string) $a->id, 3, '0', STR_PAD_LEFT),
            'staff_id' => 'STF-'.str_pad((string) ($a->provider_id ?? 0), 3, '0', STR_PAD_LEFT),
            'cliente' => $a->client?->name ?? 'Cliente',
            'servicio' => $a->producto?->nombre ?? 'Servicio',
            'precio' => '$'.number_format((int) ($a->producto?->precio_venta ?? 0), 0, ',', '.'),
            'start' => $a->start_time->hour + ($a->start_time->minute / 60),
            'duration' => max(0.5, ($a->producto?->duracion ?? 30) / 60),
        ]);

        // --- Pending payments list (financial_tasks widget) ---
        $pendingPaymentsList = $comprasPendientes
            ->take(3)
            ->map(fn ($c) => [
                'description' => $c->proveedor?->nombre ?? 'Proveedor',
                'detail' => $c->notas ?? 'Compra #'.$c->id,
                'amount' => (int) $c->total,
                'due_date' => $c->fecha?->addDays(30)->format('Y-m-d'),
            ]);

        $pendingPaymentsTotal = $pendingTotal;

        // --- Financial summary (financial_summary widget) ---
        $ventasPeriodoDia = $ingresosHoy;
        $ventasPeriodoSemana = (int) (clone $baseVentas)->whereBetween('fecha', [$inicioSemana, $hoy])->sum('total');
        $ventasPeriodoMes = (int) (clone $baseVentas)->whereBetween('fecha', [$inicioMes, $hoy])->sum('total');

        $comprasPeriodoDia = $egresosHoy;
        $comprasPeriodoSemana = (int) (clone $baseCompras)->whereBetween('fecha', [$inicioSemana, $hoy])->sum('total');
        $comprasPeriodoMes = (int) (clone $baseCompras)->whereBetween('fecha', [$inicioMes, $hoy])->sum('total');

        $financialSummary = [
            'dia' => ['ingresos' => $ventasPeriodoDia, 'egresos' => $comprasPeriodoDia],
            'semana' => ['ingresos' => $ventasPeriodoSemana, 'egresos' => $comprasPeriodoSemana],
            'mes' => ['ingresos' => $ventasPeriodoMes, 'egresos' => $comprasPeriodoMes],
        ];

        $config = DashboardConfig::where('user_id', $user->id)
            ->where('is_default', true)
            ->first();
        $savedLayout = $config ? $config->layout : null;

        return Inertia::render('Backend/Dashboard', array_merge([
            'stats' => $stats,
            'topProductos' => $topProductos,
            'mensajesSinLeer' => $mensajesSinLeer,
            'userName' => $user->name,
            'siiStats' => $siiStats,
            'productosCriticos' => $productosCriticos,
            'ventasMensuales' => $ventasMensuales,
            'almacenes' => $almacenes,
            'widgetKpis' => $widgetKpis,
            'proximasCitas' => $proximasCitas,
            'citasDelMes' => $citasDelMes,
            'savedLayout' => $savedLayout,
            'inventarioData' => $inventarioData,
            'produccionData' => $produccionData,
            'metricsSummary' => $metricsSummary,
            'staff' => $staff,
            'agendaAppointments' => $agendaAppointments,
            'agendaWeekAppointments' => $agendaWeekAppointments,
            'pendingPaymentsList' => $pendingPaymentsList,
            'pendingPaymentsTotal' => $pendingPaymentsTotal,
            'financialSummary' => $financialSummary,
        ], $masterData));
    }

    private function formatCurrency($value): string
    {
        $currency = auth()->user()->webSetting?->default_currency ?? 'CLP';

        return MoneyHelper::formatWithSymbol((float) $value, $currency);
    }

    /**
     * Compute all KPI widgets the current user is authorized to see,
     * merged with their saved visibility/order preferences.
     *
     * @return array<int, array<string, mixed>>
     */
    private function computeWidgetKpis(User $user, int $ownerId): array
    {
        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();
        $inicioMesAnterior = now()->subMonth()->startOfMonth()->toDateString();
        $finMesAnterior = now()->subMonth()->endOfMonth()->toDateString();

        // Catalog: key => [permission, label, icon, compute_fn]
        $catalog = [
            'kpi_ventas_hoy' => [
                'permission' => 'ventas.ventas.viewAny',
                'label' => 'Ventas de Hoy',
                'icon' => 'trending-up',
                'color' => 'emerald',
                'value' => null,
                'subValue' => 'Total facturado hoy',
                'href' => '/ventas',
                'format' => 'currency',
            ],
            'kpi_gastos_hoy' => [
                'permission' => 'inventario.compras.viewAny',
                'label' => 'Compras de Hoy',
                'icon' => 'shopping-cart',
                'color' => 'amber',
                'value' => null,
                'subValue' => 'Total gastado hoy',
                'href' => '/compras',
                'format' => 'currency',
            ],
            'kpi_falta_pagar' => [
                'permission' => 'inventario.compras.viewAny',
                'label' => 'Compras Pendientes',
                'icon' => 'clock',
                'color' => 'rose',
                'value' => null,
                'subValue' => 'Por pagar',
                'href' => '/compras',
                'format' => 'currency',
            ],
            'kpi_como_voy' => [
                'permission' => 'ventas.ventas.viewAny',
                'label' => 'Crecimiento de Ventas',
                'icon' => 'bar-chart',
                'color' => 'indigo',
                'value' => null,
                'subValue' => 'Vs mes anterior',
                'href' => '/ventas',
                'format' => 'percent',
            ],
            'kpi_ventas_periodo' => [
                'permission' => 'ventas.ventas.viewAny',
                'label' => 'Ventas del Mes',
                'icon' => 'calendar',
                'color' => 'blue',
                'value' => null,
                'subValue' => 'Acumulado este mes',
                'href' => '/ventas',
                'format' => 'currency',
            ],
            'kpi_clientes' => [
                'permission' => 'comercial.clientes.viewAny',
                'label' => 'Clientes Activos',
                'icon' => 'users',
                'color' => 'cyan',
                'value' => null,
                'subValue' => 'Total registrados',
                'href' => '/clientes',
                'format' => 'number',
            ],
            'kpi_proveedores' => [
                'permission' => 'inventario.proveedores.viewAny',
                'label' => 'Proveedores',
                'icon' => 'truck',
                'color' => 'violet',
                'value' => null,
                'subValue' => 'Total registrados',
                'href' => '/proveedors',
                'format' => 'number',
            ],
            'kpi_gastos_negocio' => [
                'permission' => 'inventario.compras.viewAny',
                'label' => 'Compras del Mes',
                'icon' => 'package',
                'color' => 'orange',
                'value' => null,
                'subValue' => 'Acumulado este mes',
                'href' => '/compras',
                'format' => 'currency',
            ],
            'kpi_pagos_fijos' => [
                'permission' => 'finanzas.facturacion.viewAny',
                'label' => 'Compromisos del Mes',
                'icon' => 'credit-card',
                'color' => 'pink',
                'value' => null,
                'subValue' => 'Pagos fijos realizados',
                'href' => '/facturacion',
                'format' => 'currency',
            ],
            'kpi_cuentas_por_cobrar' => [
                'permission' => 'finanzas.facturacion.viewAny',
                'label' => 'Por Cobrar',
                'icon' => 'receipt',
                'color' => 'teal',
                'value' => null,
                'subValue' => 'Facturas pendientes',
                'href' => '/facturacion',
                'format' => 'currency',
            ],
        ];

        // Load user widget preferences
        $userPrefs = UserDashboardWidget::where('user_id', $user->id)
            ->get()
            ->keyBy('widget_key');

        $widgets = [];

        foreach ($catalog as $key => $config) {
            if (! $user->can($config['permission'])) {
                continue;
            }

            $pref = $userPrefs->get($key);
            $settings = $pref ? ($pref->settings ?? []) : [];
            $visible = isset($settings['visible']) ? (bool) $settings['visible'] : true;
            $orderIndex = $pref ? $pref->order_index : 99;

            // Compute value lazily based on widget type
            $value = match ($key) {
                'kpi_ventas_hoy' => (int) Venta::where('estado', 'pagada')
                    ->whereDate('fecha', $hoy)
                    ->where('owner_id', $ownerId)
                    ->sum('total'),

                'kpi_gastos_hoy' => (int) Compra::whereDate('fecha', $hoy)
                    ->where('owner_id', $ownerId)
                    ->sum('total'),

                'kpi_falta_pagar' => (int) Compra::where('estado', 'pendiente')
                    ->where('owner_id', $ownerId)
                    ->sum('total'),

                'kpi_como_voy' => $this->computeComoVoy($ownerId, $hoy, $inicioMes, $inicioMesAnterior, $finMesAnterior),

                'kpi_ventas_periodo' => (int) Venta::where('estado', 'pagada')
                    ->whereBetween('fecha', [$inicioMes, $hoy])
                    ->where('owner_id', $ownerId)
                    ->sum('total'),

                'kpi_clientes' => Cliente::where('owner_id', $ownerId)->count(),

                'kpi_proveedores' => Proveedor::where('owner_id', $ownerId)->count(),

                'kpi_gastos_negocio' => (int) Compra::whereBetween('fecha', [$inicioMes, $hoy])
                    ->where('owner_id', $ownerId)
                    ->sum('total'),

                'kpi_pagos_fijos' => (int) Pago::whereMonth('fecha_pago', now()->month)
                    ->whereYear('fecha_pago', now()->year)
                    ->where('owner_id', $ownerId)
                    ->sum('monto'),

                'kpi_cuentas_por_cobrar' => (int) Factura::where('estado', 'pendiente')
                    ->where('owner_id', $ownerId)
                    ->sum('total'),

                default => 0,
            };

            $widgets[] = [
                'key' => $key,
                'label' => $config['label'],
                'icon' => $config['icon'],
                'color' => $config['color'],
                'value' => $value,
                'subValue' => $config['subValue'],
                'href' => $config['href'],
                'format' => $config['format'],
                'visible' => $visible,
                'order_index' => $orderIndex,
            ];
        }

        usort($widgets, fn ($a, $b) => $a['order_index'] <=> $b['order_index']);

        return $widgets;
    }

    /**
     * Compute percentage growth vs previous month.
     */
    private function computeComoVoy(
        int $ownerId,
        string $hoy,
        string $inicioMes,
        string $inicioMesAnterior,
        string $finMesAnterior,
    ): string {
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

            return ($diferencia >= 0 ? '+' : '').number_format($diferencia, 1).'%';
        }

        return $ventasMes > 0 ? '+Nuevo' : '0%';
    }

    public function saveConfig(Request $request)
    {
        $user = Auth::user();

        $data = [
            'mode' => $request->input('mode', 'grid'),
            'is_default' => true,
        ];

        $widgets = $request->input('widgets');
        if ($widgets) {
            if (is_string($widgets)) {
                $data['widgets'] = json_decode($widgets, true);
            } else {
                $data['widgets'] = $widgets;
            }
        }

        $layout = $request->input('layout');
        if ($layout) {
            if (is_string($layout)) {
                $data['layout'] = json_decode($layout, true);
            } else {
                $data['layout'] = $layout;
            }
        }

        $config = DashboardConfig::where('user_id', $user->id)
            ->where('is_default', true)
            ->first();

        if ($config) {
            $config->update($data);
        } else {
            DashboardConfig::create(array_merge($data, [
                'user_id' => $user->id,
            ]));
        }

        return back()->with('success', 'Configuración guardada');
    }

    /**
     * Toggle a widget's visibility for the current user.
     */
    public function toggleWidget(Request $request): RedirectResponse
    {
        $request->validate(['key' => 'required|string']);

        $userId = Auth::id();
        $key = $request->input('key');

        $pref = UserDashboardWidget::where('user_id', $userId)->where('widget_key', $key)->first();
        $settings = $pref ? ($pref->settings ?? []) : [];
        $settings['visible'] = isset($settings['visible']) ? ! $settings['visible'] : false;

        UserDashboardWidget::updateOrCreate(
            ['user_id' => $userId, 'widget_key' => $key],
            ['settings' => $settings]
        );

        return back();
    }

    /**
     * Persist new widget order after drag-and-drop.
     *
     * @param  array<string>  $keys
     */
    public function reorderWidgets(Request $request): RedirectResponse
    {
        $request->validate(['keys' => 'required|array', 'keys.*' => 'string']);

        $userId = Auth::id();

        foreach ($request->input('keys') as $index => $key) {
            UserDashboardWidget::updateOrCreate(
                ['user_id' => $userId, 'widget_key' => $key],
                ['order_index' => $index]
            );
        }

        return back();
    }
}
