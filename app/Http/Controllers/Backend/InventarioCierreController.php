<?php

namespace App\Http\Controllers\Backend;

use App\Exports\CierresInventarioExport;
use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\InventoryClosure;
use App\Models\Vacio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class InventarioCierreController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventario.inventarios.create', only: ['create', 'store']),
            new Middleware('permission:inventario.inventarios.edit', only: ['edit', 'update']),
            new Middleware('permission:inventario.inventarios.delete', only: ['destroy']),
            new Middleware('permission:inventario.inventarios.export', only: ['exportCsv', 'exportExcel']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();

        $query = InventoryClosure::where('owner_id', $ownerId)
            ->with(['user', 'almacen'])
            ->orderBy('closure_date', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('almacen_id')) {
            $query->where('almacen_id', $request->almacen_id);
        }

        if ($request->boolean('difference')) {
            $query->where('difference', '!=', 0);
        }

        $cierres = $query->paginate(15)->withQueryString();

        $almacenes = Almacen::where('owner_id', $ownerId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Backend/Inventarios/Cierre/Index', [
            'cierres' => $cierres,
            'almacenes' => $almacenes,
            'filters' => $request->only(['status', 'almacen_id', 'difference']),
        ]);
    }

    public function create(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();

        $almacenes = Almacen::where('owner_id', $ownerId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $almacenId = $request->input('almacen_id');

        $productos = Inventario::select('inventarios.*')
            ->whereHas('producto', fn ($q) => $q->where('is_service', false))
            ->with(['producto', 'almacen'])
            ->when($almacenId, function ($query) use ($almacenId) {
                $query->where('almacen_id', $almacenId);
            })
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'producto_id' => $inv->producto_id,
                    'producto_nombre' => $inv->producto?->nombre,
                    'producto_codigo' => $inv->producto?->codigo,
                    'almacen_id' => $inv->almacen_id,
                    'almacen_nombre' => $inv->almacen?->nombre,
                    'stock_actual' => (float) $inv->cantidad,
                    'stock_minimo' => (float) $inv->cantidad_minima,
                ];
            });

        $ventasHoy = $this->calcularVentasHoy($ownerId, $almacenId);

        // Envases pendientes de retorno (estado "entregado" en Vacio)
        $envasesPendientes = Vacio::with('producto')
            ->where('owner_id', $ownerId)
            ->where('estado', 'entregado')
            ->when($almacenId, function ($query) use ($almacenId) {
                $query->whereHas('producto', function ($q) use ($almacenId) {
                    $q->whereHas('inventarios', function ($qi) use ($almacenId) {
                        $qi->where('almacen_id', $almacenId);
                    });
                });
            })
            ->orderBy('cantidad', 'desc')
            ->get()
            ->map(fn ($v) => [
                'id' => $v->id,
                'producto' => $v->producto?->nombre ?? 'N/A',
                'cantidad' => $v->cantidad,
                'observaciones' => $v->observaciones,
            ]);

        return Inertia::render('Backend/Inventarios/Cierre/Create', [
            'almacenes' => $almacenes,
            'productos' => $productos,
            'ventas_esperadas' => $ventasHoy,
            'envases_pendientes' => $envasesPendientes,
            'selected_almacen' => $almacenId,
        ]);
    }

    private function calcularVentasHoy(int $ownerId, ?int $almacenId): array
    {
        $ventasQuery = DetalleVenta::whereHas('venta', function ($query) use ($ownerId, $almacenId) {
            $query->where('owner_id', $ownerId)
                ->whereDate('created_at', now()->toDateString());
            if ($almacenId) {
                $query->where('almacen_id', $almacenId);
            }
        })
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->leftJoin('almacenes', 'ventas.almacen_id', '=', 'almacenes.id')
            ->selectRaw('detalle_ventas.producto_id, ventas.almacen_id, almacenes.nombre as almacen_nombre, SUM(detalle_ventas.cantidad) as total_vendido')
            ->groupBy('detalle_ventas.producto_id', 'ventas.almacen_id', 'almacenes.nombre')
            ->get();

        $ventasAgrupadas = [];
        foreach ($ventasQuery as $v) {
            $key = $v->producto_id;
            if (! isset($ventasAgrupadas[$key])) {
                $ventasAgrupadas[$key] = [
                    'total_vendido' => 0,
                    'por_almacen' => [],
                ];
            }
            $ventasAgrupadas[$key]['total_vendido'] += $v->total_vendido;
            if ($v->almacen_id) {
                $ventasAgrupadas[$key]['por_almacen'][$v->almacen_id] = [
                    'nombre' => $v->almacen_nombre ?? 'Sin bodega',
                    'cantidad' => $v->total_vendido,
                ];
            }
        }

        return $ventasAgrupadas;
    }

    public function store(Request $request): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'almacen_id' => 'nullable|exists:almacenes,id',
            'type' => 'required|in:BODEGA,GENERAL',
            'total_products' => 'required|integer|min:0',
            'opening_stock' => 'required|numeric|min:0',
            'closing_stock' => 'required|numeric|min:0',
            'expected_stock' => 'required|numeric|min:0',
            'difference' => 'required|numeric',
            'observations' => 'nullable|string|max:500',
            'marcar_auditado' => 'boolean',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['owner_id'] = $ownerId;
        $validated['closure_date'] = now()->toDateString();
        $validated['status'] = $validated['marcar_auditado'] ? 'AUDITADO' : 'CERRADO';

        if ($validated['status'] === 'AUDITADO') {
            $validated['closed_at'] = now();
        }

        unset($validated['marcar_auditado']);

        InventoryClosure::create($validated);

        return redirect()->route('inventario-cierre.index')
            ->with('success', 'Cierre de inventario registrado correctamente.');
    }

    public function show(InventoryClosure $cierre): Response
    {
        $cierre->load(['user', 'almacen']);

        return Inertia::render('Backend/Inventarios/Cierre/Show', [
            'cierre' => $cierre,
        ]);
    }

    public function update(Request $request, InventoryClosure $cierre): RedirectResponse
    {
        $validated = $request->validate([
            'observations' => 'nullable|string|max:1000',
            'expected_stock' => 'nullable|numeric|min:0',
        ]);

        $cierre->update([
            'observations' => $validated['observations'],
            'expected_stock' => $validated['expected_stock'] ?? $cierre->expected_stock,
        ]);

        return redirect()->back()->with('success', 'Cierre actualizado correctamente.');
    }

    public function audit(InventoryClosure $cierre): RedirectResponse
    {
        $cierre->update([
            'status' => 'AUDITADO',
            'closed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Cierre auditado correctamente.');
    }

    public function destroy(InventoryClosure $cierre): RedirectResponse
    {
        if ($cierre->status === 'AUDITADO') {
            return redirect()->back()->with('error', 'No se puede eliminar un cierre auditado.');
        }

        $cierre->delete();

        return redirect()->route('inventario-cierre.index')
            ->with('success', 'Cierre eliminado correctamente.');
    }

    public function exportCsv()
    {
        $ownerId = Auth::user()->getOwnerId();

        $cierres = InventoryClosure::where('owner_id', $ownerId)
            ->with(['user', 'almacen'])
            ->orderBy('closure_date', 'desc')
            ->get();

        $headers = ['Fecha', 'Tipo', 'Bodega', 'Productos', 'Stock Actual', 'Esperado', 'Diferencia', 'Estado', 'Usuario'];

        $rows = $cierres->map(function ($cierre) {
            return [
                $cierre->closure_date,
                $cierre->type,
                $cierre->almacen?->nombre ?? 'General',
                $cierre->total_products,
                $cierre->total_stock,
                $cierre->expected_stock,
                $cierre->difference,
                $cierre->status,
                $cierre->user?->name,
            ];
        });

        return response()->stream(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="cierres_inventario.csv"',
        ]);
    }

    public function exportExcel()
    {
        $ownerId = Auth::user()->getOwnerId();

        $cierres = InventoryClosure::where('owner_id', $ownerId)
            ->with(['user', 'almacen'])
            ->orderBy('closure_date', 'desc')
            ->get();

        $data = $cierres->map(function ($cierre) {
            return [
                'Fecha' => $cierre->closure_date,
                'Tipo' => $cierre->type,
                'Bodega' => $cierre->almacen?->nombre ?? 'General',
                'Productos' => $cierre->total_products,
                'Stock Actual' => $cierre->total_stock,
                'Esperado' => $cierre->expected_stock,
                'Diferencia' => $cierre->difference,
                'Estado' => $cierre->status,
                'Usuario' => $cierre->user?->name,
            ];
        });

        return Excel::download(
            new CierresInventarioExport($data),
            'cierres_inventario.xlsx'
        );
    }
}
