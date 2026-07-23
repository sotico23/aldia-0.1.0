<?php

namespace App\Http\Controllers\Backend;

use App\Exports\InventariosExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Imports\InventariosImport;
use App\Models\Almacen;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventarioController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventario.inventarios.create', only: ['create', 'store']),
            new Middleware('permission:inventario.inventarios.edit', only: ['edit', 'update']),
            new Middleware('permission:inventario.inventarios.delete', only: ['destroy']),
            new Middleware('permission:inventario.inventarios.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:inventario.inventarios.import', only: ['importCsv', 'importExcel']),
        ];
    }

    /**
     * Get net product sold in the last 30 days (excluding container considerations for now)
     */
    private function getNetProductSold(int $productoId): float
    {
        return DetalleVenta::where('producto_id', $productoId)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('venta', fn ($q) => $q->where('owner_id', Auth::user()->getOwnerId()))
            ->sum('cantidad');
    }

    public function index(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();

        $almacenes = Almacen::where('activo', true)
            ->where('owner_id', $ownerId)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $almacenId = $request->input('almacen_id');

        // Main query starts from Producto to ensure all products are reflected
        $query = Producto::where('productos.owner_id', $ownerId)
            ->where('productos.activo', true)
            ->where('productos.is_service', false)
            ->leftJoin('inventarios', function ($join) use ($ownerId) {
                $join->on('productos.id', '=', 'inventarios.producto_id')
                    ->where('inventarios.owner_id', '=', $ownerId);
            })
            ->select('productos.*')
            ->with(['categoria']);

        // Filtering by warehouse if selected
        if ($almacenId && $almacenId !== 'all') {
            $query->where('inventarios.almacen_id', $almacenId);
        }

        // Search filter
        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('productos.nombre', 'like', "%{$search}%")
                    ->orWhere('productos.codigo', 'like', "%{$search}%");
            });
        }

        // Consolidated Stock Calculation
        $query->selectRaw('SUM(inventarios.cantidad) as total_stock')
            ->groupBy('productos.id');

        // Low Stock Filter - Per warehouse when filtered, consolidated otherwise
        if ($request->boolean('stock_bajo')) {
            if ($almacenId && $almacenId !== 'all') {
                // When filtering by a specific warehouse, the JOIN keeps only that
                // warehouse's row, so the grouped SUM equals its stock. Evaluate the
                // low-stock condition against that warehouse's own minimum (cantidad_minima).
                $query->havingRaw('SUM(inventarios.cantidad) <= MAX(inventarios.cantidad_minima)');
            } else {
                // Consolidated stock check
                $query->havingRaw('total_stock <= productos.stock_minimo OR total_stock IS NULL');
            }
        }

        $productos = $query->with('inventarios.almacen:id,nombre')
            ->orderBy('productos.nombre')
            ->paginate(15)
            ->withQueryString();

        // Calculate projections for each product in the current page
        $productsWithProjections = $productos->getCollection()->map(function ($producto) {
            // Last 30 days sales (net product sold)
            $salesLast30Days = $this->getNetProductSold($producto->id);

            $avgDailySales = $salesLast30Days / 30;
            $stockActual = $producto->total_stock ?? 0;

            $daysRemaining = $avgDailySales > 0
                ? floor($stockActual / $avgDailySales)
                : 999; // Sufficient stock indefinitely

            // Get inventory details by warehouse
            $inventariosPorAlmacen = ($producto->inventarios ?? collect())
                ->map(function ($inv) {
                    return [
                        'almacen' => $inv->almacen?->nombre,
                        'cantidad' => (float) $inv->cantidad,
                        'cantidad_minima' => (float) $inv->cantidad_minima,
                        'ubicacion' => $inv->ubicacion,
                    ];
                });

            return [
                'id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->nombre,
                'categoria' => $producto->categoria?->nombre,
                'total_stock' => (float) $stockActual,
                'stock_minimo' => (float) $producto->stock_minimo,
                'avg_daily_sales' => round($avgDailySales, 2),
                'days_remaining' => $daysRemaining,
                'status' => $stockActual <= $producto->stock_minimo ? 'low' : 'optimal',
                'imagen' => $producto->imagen,
                'inventarios' => $inventariosPorAlmacen,
                'projection' => [
                    ['name' => 'Hoy', 'stock' => (float) $stockActual],
                    ['name' => '10 d', 'stock' => max(0, round($stockActual - ($avgDailySales * 10), 2))],
                    ['name' => '20 d', 'stock' => max(0, round($stockActual - ($avgDailySales * 20), 2))],
                    ['name' => '30 d', 'stock' => max(0, round($stockActual - ($avgDailySales * 30), 2))],
                ],
            ];
        });

        $productos->setCollection($productsWithProjections);

        $productosParaModal = Producto::where('productos.activo', true)
            ->where('productos.owner_id', $ownerId)
            ->where('productos.is_service', false)
            ->with(['inventarios' => function ($query) use ($ownerId) {
                $query->where('owner_id', $ownerId)
                    ->select('id', 'producto_id', 'almacen_id', 'cantidad', 'cantidad_minima', 'ubicacion');
            }])
            ->leftJoin('inventarios', function ($join) use ($ownerId) {
                $join->on('productos.id', '=', 'inventarios.producto_id')
                    ->where('inventarios.owner_id', '=', $ownerId);
            })
            ->select('productos.id', 'productos.nombre', 'productos.codigo', 'productos.stock_minimo', 'productos.imagen')
            ->selectRaw('COALESCE(SUM(inventarios.cantidad), 0) as total_stock')
            ->groupBy('productos.id', 'productos.nombre', 'productos.codigo', 'productos.stock_minimo', 'productos.imagen')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'codigo' => $p->codigo,
                    'total_stock' => (int) $p->total_stock,
                    'stock_minimo' => (int) $p->stock_minimo,
                    'imagen' => $p->imagen,
                    'inventarios' => $p->inventarios->map(function ($inv) {
                        return [
                            'almacen_id' => $inv->almacen_id,
                            'cantidad' => (float) $inv->cantidad,
                            'cantidad_minima' => (float) $inv->cantidad_minima,
                            'ubicacion' => $inv->ubicacion,
                        ];
                    }),
                ];
            });

        $stockPorBodega = Almacen::where('almacenes.owner_id', $ownerId)
            ->leftJoin('inventarios', 'almacenes.id', '=', 'inventarios.almacen_id')
            ->select('almacenes.id', 'almacenes.nombre')
            ->selectRaw('COALESCE(SUM(inventarios.cantidad), 0) as total_stock')
            ->groupBy('almacenes.id', 'almacenes.nombre')
            ->get()
            ->map(fn ($a) => ['nombre' => $a->nombre, 'stock' => (int) $a->total_stock]);

        $productosStockBajo = Inventario::where('inventarios.owner_id', $ownerId)
            ->join('productos', 'inventarios.producto_id', '=', 'productos.id')
            ->join('almacenes', 'inventarios.almacen_id', '=', 'almacenes.id')
            ->whereRaw('inventarios.cantidad < inventarios.cantidad_minima')
            ->select('productos.nombre', 'almacenes.nombre as almacen', 'inventarios.cantidad', 'inventarios.cantidad_minima')
            ->orderByRaw('inventarios.cantidad_minima - inventarios.cantidad DESC')
            ->limit(10)
            ->get()
            ->map(fn ($p) => ['nombre' => $p->nombre, 'almacen' => $p->almacen, 'stock' => (int) $p->cantidad, 'minimo' => (int) $p->cantidad_minima]);

        $productosTopStock = $productos->sortByDesc('total_stock')
            ->take(10)
            ->map(fn ($p) => ['nombre' => $p['nombre'], 'stock' => $p['total_stock']])
            ->values();

        $ventasPorFechaYAlmacen = \DB::table('ventas')
            ->selectRaw('DATE(ventas.fecha) as fecha, almacenes.nombre as almacen, SUM(detalle_ventas.cantidad) as cantidad_vendida')
            ->join('detalle_ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->leftJoin('almacenes', 'ventas.almacen_id', '=', 'almacenes.id')
            ->where('ventas.owner_id', $ownerId)
            ->where('ventas.fecha', '>=', now()->subDays(30))
            ->where('ventas.estado', 'pagada')
            ->groupBy('fecha', 'almacenes.nombre')
            ->orderBy('fecha')
            ->orderBy('almacenes.nombre')
            ->get()
            ->map(fn ($v) => [
                'fecha' => $v->fecha,
                'almacen' => $v->almacen ?? 'Sin bodega',
                'cantidad' => (int) $v->cantidad_vendida,
            ]);

        $ventasPorFecha = \DB::table('ventas')
            ->selectRaw('DATE(ventas.fecha) as fecha, SUM(detalle_ventas.cantidad) as total_vendido')
            ->join('detalle_ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->where('ventas.owner_id', $ownerId)
            ->where('ventas.fecha', '>=', now()->subDays(30))
            ->where('ventas.estado', 'pagada')
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->map(fn ($v) => ['fecha' => $v->fecha, 'ventas' => (int) $v->total_vendido]);

        return Inertia::render('Backend/Inventarios/Index', [
            'inventarios' => $productos,
            'almacenes' => $almacenes,
            'productos' => $productosParaModal,
            'filters' => $request->only(['search', 'stock_bajo', 'almacen_id']),
            'stockPorBodega' => $stockPorBodega,
            'productosStockBajo' => $productosStockBajo,
            'productosTopStock' => $productosTopStock,
            'ventasPorFecha' => $ventasPorFecha,
            'ventasPorAlmacen' => $ventasPorFechaYAlmacen,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'producto_id' => ['required', Rule::exists('productos', 'id')->where('owner_id', $ownerId)],
            'almacen_id' => ['required', Rule::exists('almacenes', 'id')->where('owner_id', $ownerId)],
            'cantidad' => 'required|numeric|min:0',
            'cantidad_minima' => 'required|numeric|min:0',
            'ubicacion' => 'nullable|string|max:100',
        ]);

        $producto = Producto::where('id', $validated['producto_id'])
            ->where('owner_id', $ownerId)
            ->firstOrFail();

        $almacen = Almacen::where('id', $validated['almacen_id'])
            ->where('owner_id', $ownerId)
            ->firstOrFail();

        $inventarioExistente = Inventario::where('producto_id', $validated['producto_id'])
            ->where('almacen_id', $validated['almacen_id'])
            ->first();

        Inventario::updateOrCreate(
            [
                'producto_id' => $validated['producto_id'],
                'almacen_id' => $validated['almacen_id'],
            ],
            [
                'owner_id' => $ownerId,
                'cantidad' => $validated['cantidad'],
                'cantidad_minima' => $validated['cantidad_minima'],
                'ubicacion' => $validated['ubicacion'] ?? null,
            ]
        );

        return redirect()->route('inventarios.index')->with('success', 'Inventario actualizado correctamente.');
    }

    public function update(Request $request, Inventario $inventario): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $inventario->load('producto');

        if ($inventario->producto->owner_id !== $ownerId) {
            abort(403);
        }

        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'almacen_id' => 'required|exists:almacenes,id',
            'cantidad' => 'required|numeric|min:0',
            'cantidad_minima' => 'required|numeric|min:0',
            'ubicacion' => 'nullable|string|max:100',
        ]);

        $inventario->update([
            'cantidad' => $validated['cantidad'],
            'cantidad_minima' => $validated['cantidad_minima'],
            'ubicacion' => $validated['ubicacion'] ?? null,
        ]);

        return redirect()->route('inventarios.index');
    }

    public function destroy(Inventario $inventario): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $inventario->load('producto');

        if ($inventario->producto->owner_id !== $ownerId) {
            abort(403);
        }

        $inventario->delete();

        return redirect()->route('inventarios.index');
    }

    public function show(Inventario $inventario): Response
    {
        $ownerId = Auth::user()->getOwnerId();

        $inventario->load(['producto.categoria', 'almacen']);

        if ($inventario->producto->owner_id !== $ownerId) {
            abort(403);
        }

        return Inertia::render('Backend/Inventarios/Show', [
            'inventario' => $inventario,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $ownerId = auth()->user()->getOwnerId();

        $almacenes = Almacen::where('activo', true)
            ->where('owner_id', $ownerId)
            ->pluck('id')
            ->toArray();

        $query = Inventario::whereHas('producto', fn ($q) => $q->where('owner_id', $ownerId)->where('is_service', false))
            ->whereIn('almacen_id', $almacenes)
            ->with(['producto', 'almacen']);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('producto', fn ($pq) => $pq->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                )->orWhere('ubicacion', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('stock_bajo')) {
            $query->whereColumn('cantidad', '<=', 'cantidad_minima');
        }

        if ($request->filled('almacen_id')) {
            $query->where('almacen_id', $request->input('almacen_id'));
        }

        $inventarios = $query->orderBy('created_at', 'desc')->get();

        $almacenFiltrado = $request->filled('almacen_id')
            ? Almacen::where('id', $request->input('almacen_id'))->value('nombre')
            : null;

        $tipoReporte = $almacenFiltrado
            ? 'Bodega: '.$almacenFiltrado
            : 'Consolidado (Todas las bodegas)';

        $filename = 'inventarios_'.($almacenFiltrado ? Str::slug($almacenFiltrado) : 'consolidado').'_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($inventarios, $tipoReporte) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, [
                'Tipo de Reporte',
                'Producto',
                'SKU',
                'Almacén',
                'Cantidad',
                'Stock Mínimo',
                'Ubicación',
            ], ';');

            foreach ($inventarios as $inv) {
                fputcsv($file, [
                    $tipoReporte,
                    $inv->producto?->nombre ?? '',
                    $inv->producto?->sku ?? '',
                    $inv->almacen?->nombre ?? '',
                    $inv->cantidad,
                    $inv->cantidad_minima,
                    $inv->ubicacion ?? '',
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportExcel(Request $request)
    {
        $ownerId = auth()->user()->getOwnerId();

        $almacenes = Almacen::where('activo', true)
            ->where('owner_id', $ownerId)
            ->pluck('id')
            ->toArray();

        $query = Inventario::whereHas('producto', fn ($q) => $q->where('owner_id', $ownerId)->where('is_service', false))
            ->whereIn('almacen_id', $almacenes)
            ->with(['producto', 'almacen']);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('producto', fn ($pq) => $pq->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                )->orWhere('ubicacion', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('stock_bajo')) {
            $query->whereColumn('cantidad', '<=', 'cantidad_minima');
        }

        if ($request->filled('almacen_id')) {
            $query->where('almacen_id', $request->input('almacen_id'));
        }

        $inventarios = $query->orderBy('created_at', 'desc')->get();

        $almacenFiltrado = $request->filled('almacen_id')
            ? Almacen::where('id', $request->input('almacen_id'))->value('nombre')
            : null;

        $filename = 'inventarios_'.($almacenFiltrado ? Str::slug($almacenFiltrado) : 'consolidado').'_'.now()->format('Ymd_His').'.xlsx';

        $tipoReporte = $almacenFiltrado
            ? 'Bodega: '.$almacenFiltrado
            : 'Consolidado (Todas las bodegas)';

        return Excel::download(new InventariosExport($inventarios, $tipoReporte), $filename);
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt',
        ]);

        Excel::import(new InventariosImport, $request->file('archivo'));

        return redirect()->route('inventarios.index')->with('success', 'Inventarios importadas correctamente.');
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls',
        ]);

        Excel::import(new InventariosImport, $request->file('archivo'));

        return redirect()->route('inventarios.index')->with('success', 'Inventarios importadas correctamente.');
    }
}
