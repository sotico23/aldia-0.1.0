<?php

namespace App\Http\Controllers\Backend;

use App\Exports\MovimientosExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Imports\MovimientosImport;
use App\Models\Almacen;
use App\Models\Movimiento;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovimientoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventario.movimientos.create', only: ['create', 'store']),
            new Middleware('permission:inventario.movimientos.edit', only: ['edit', 'update']),
            new Middleware('permission:inventario.movimientos.delete', only: ['destroy']),
            new Middleware('permission:inventario.movimientos.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:inventario.movimientos.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();

        $query = Movimiento::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('producto', 'like', "%{$search}%")
                    ->orWhere('tipo', 'like', "%{$search}%")
                    ->orWhere('referencia', 'like', "%{$search}%")
                    ->orWhere('almacen_origen', 'like', "%{$search}%")
                    ->orWhere('almacen_destino', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        $movimientos = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $productos = Producto::with('inventarios.almacen')
            ->where('owner_id', $ownerId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'codigo' => $p->codigo,
                'precio_venta' => $p->precio_venta,
                'inventarios' => $p->inventarios->map(fn ($inv) => [
                    'id' => $inv->id,
                    'cantidad' => $inv->cantidad,
                    'almacen_id' => $inv->almacen_id,
                    'almacen_nombre' => $inv->almacen?->nombre ?? 'Sin almacén',
                ]),
            ]);

        $almacenes = Almacen::where('owner_id', $ownerId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'nombre' => $a->nombre,
            ]);

        return Inertia::render('Backend/Movimientos/Index', [
            'movimientos' => $movimientos,
            'productos' => $productos,
            'almacenes' => $almacenes,
            'filters' => $request->only(['search', 'tipo']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'tipo' => 'required|string|max:50',
            'cantidad' => 'required|integer|min:1',
            'almacen_origen_id' => 'nullable|exists:almacenes,id',
            'almacen_destino_id' => 'nullable|exists:almacenes,id',
            'referencia' => 'nullable|string|max:255',
            'notas' => 'nullable|string',
        ]);

        $producto = Producto::findOrFail($validated['producto_id']);
        $validated['producto'] = $producto->nombre;

        if (! empty($validated['almacen_origen_id'])) {
            $almacen = Almacen::findOrFail($validated['almacen_origen_id']);
            $validated['almacen_origen'] = $almacen->nombre;
        }

        if (! empty($validated['almacen_destino_id'])) {
            $almacen = Almacen::findOrFail($validated['almacen_destino_id']);
            $validated['almacen_destino'] = $almacen->nombre;
        }

        $validated['owner_id'] = $ownerId;
        Movimiento::create($validated);

        return redirect()->route('movimientos.index');
    }

    public function update(Request $request, Movimiento $movimiento): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        if ($movimiento->owner_id !== $ownerId) {
            abort(403);
        }

        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'tipo' => 'required|string|max:50',
            'cantidad' => 'required|integer|min:1',
            'almacen_origen_id' => 'nullable|exists:almacenes,id',
            'almacen_destino_id' => 'nullable|exists:almacenes,id',
            'referencia' => 'nullable|string|max:255',
            'notas' => 'nullable|string',
        ]);

        $producto = Producto::findOrFail($validated['producto_id']);
        $validated['producto'] = $producto->nombre;

        if (! empty($validated['almacen_origen_id'])) {
            $almacen = Almacen::findOrFail($validated['almacen_origen_id']);
            $validated['almacen_origen'] = $almacen->nombre;
        } else {
            $validated['almacen_origen'] = null;
            $validated['almacen_origen_id'] = null;
        }

        if (! empty($validated['almacen_destino_id'])) {
            $almacen = Almacen::findOrFail($validated['almacen_destino_id']);
            $validated['almacen_destino'] = $almacen->nombre;
        } else {
            $validated['almacen_destino'] = null;
            $validated['almacen_destino_id'] = null;
        }

        $movimiento->update($validated);

        return redirect()->route('movimientos.index');
    }

    public function destroy(Movimiento $movimiento): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        if ($movimiento->owner_id !== $ownerId) {
            abort(403);
        }

        $movimiento->delete();

        return redirect()->route('movimientos.index');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        $query = Movimiento::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('producto', 'like', "%{$search}%")
                    ->orWhere('tipo', 'like', "%{$search}%")
                    ->orWhere('referencia', 'like', "%{$search}%");
            });
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();
        $filename = 'movimientos_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($movimientos) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, [
                'Producto',
                'Tipo',
                'Cantidad',
                'Origen',
                'Destino',
                'Referencia',
                'Notas',
                'Fecha',
            ], ';');

            foreach ($movimientos as $mov) {
                fputcsv($file, [
                    $mov->producto,
                    $mov->tipo,
                    $mov->cantidad,
                    $mov->almacen_origen,
                    $mov->almacen_destino,
                    $mov->referencia,
                    $mov->notas,
                    $mov->created_at->format('Y-m-d H:i'),
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportExcel(Request $request)
    {
        $ownerId = Auth::user()->getOwnerId();
        $query = Movimiento::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('producto', 'like', "%{$search}%")
                    ->orWhere('tipo', 'like', "%{$search}%")
                    ->orWhere('referencia', 'like', "%{$search}%");
            });
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();

        return Excel::download(new MovimientosExport($movimientos), 'movimientos_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt',
        ]);

        Excel::import(new MovimientosImport, $request->file('archivo'));

        return redirect()->route('movimientos.index')->with('success', 'Movimientos importados correctamente.');
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls',
        ]);

        Excel::import(new MovimientosImport, $request->file('archivo'));

        return redirect()->route('movimientos.index')->with('success', 'Movimientos importados correctamente.');
    }
}
