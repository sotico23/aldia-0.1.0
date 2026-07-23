<?php

namespace App\Http\Controllers\Backend;

use App\Exports\ComprasExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Imports\ComprasImport;
use App\Models\Almacen;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\WebSetting;
use Barryvdh\DomPDF\Facade\Pdf;
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

class CompraController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:inventario.compras.create', only: ['create', 'store']),
            new Middleware('permission:inventario.compras.edit', only: ['edit', 'update']),
            new Middleware('permission:inventario.compras.delete', only: ['destroy']),
            new Middleware('permission:inventario.compras.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:inventario.compras.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();
        $query = Compra::with('proveedor', 'detalleCompras.producto')
            ->where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('proveedor', function ($pq) use ($search) {
                        $pq->where('nombre', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $compras = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $proveedors = Proveedor::where('activo', true)->where('owner_id', $ownerId)->get();
        $productos = Producto::where('activo', true)->where('owner_id', $ownerId)->get();
        $almacenes = Almacen::where('activo', true)->where('owner_id', $ownerId)->get(['id', 'nombre']);

        return Inertia::render('Backend/Compras/Index', [
            'compras' => $compras,
            'proveedors' => $proveedors,
            'productos' => $productos,
            'almacenes' => $almacenes,
            'filters' => $request->only(['search', 'estado']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'numero' => 'required|string|max:50|unique:compras,numero',
            'proveedor_id' => ['required', Rule::exists('proveedors', 'id')->where('owner_id', $ownerId)],
            'fecha' => 'required|date',
            'estado' => 'required|in:pendiente,recibida,cancelada',
            'notas' => 'nullable|string',
            'almacen_id' => ['required', Rule::exists('almacenes', 'id')->where('owner_id', $ownerId)],
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => ['required', Rule::exists('productos', 'id')->where('owner_id', $ownerId)],
            'productos.*.cantidad' => 'required|integer|min:1',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
        ]);

        $subtotal = 0;
        foreach ($validated['productos'] as $item) {
            $subtotal += $item['cantidad'] * $item['precio_unitario'];
        }
        $iva = round($subtotal * config('taxes.iva_rate'));
        $total = $subtotal + $iva;

        $compra = Compra::create([
            'owner_id' => $ownerId,
            'numero' => $validated['numero'],
            'proveedor_id' => $validated['proveedor_id'],
            'fecha' => $validated['fecha'],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'total' => $total,
            'estado' => $validated['estado'],
            'notas' => $validated['notas'] ?? null,
            'almacen_id' => $validated['almacen_id'],
        ]);

        foreach ($validated['productos'] as $item) {
            $subtotalItem = $item['cantidad'] * $item['precio_unitario'];

            DetalleCompra::create([
                'compra_id' => $compra->id,
                'producto_id' => $item['producto_id'],
                'cantidad' => $item['cantidad'],
                'precio_unitario' => $item['precio_unitario'],
                'subtotal' => $subtotalItem,
            ]);

            if ($validated['estado'] === 'recibida') {
                $inventario = Inventario::firstOrCreate(
                    [
                        'producto_id' => $item['producto_id'],
                        'almacen_id' => $validated['almacen_id'],
                    ],
                    [
                        'owner_id' => $ownerId,
                        'cantidad' => 0,
                        'cantidad_minima' => 0,
                    ]
                );
                $inventario->increment('cantidad', $item['cantidad']);
            }
        }

        return redirect()->route('compras.index');
    }

    public function update(Request $request, Compra $compra): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();

        $validated = $request->validate([
            'estado' => 'required|in:pendiente,recibida,cancelada',
            'notas' => 'nullable|string',
            'almacen_id' => ['nullable', Rule::exists('almacenes', 'id')->where('owner_id', $ownerId)],
        ]);

        $estadoAnterior = $compra->estado;
        $compra->update($validated);

        $almacenId = $validated['almacen_id'] ?? $compra->almacen_id;

        if ($estadoAnterior !== 'recibida' && $validated['estado'] === 'recibida') {
            $compra->load('detalleCompras');
            foreach ($compra->detalleCompras as $detalle) {
                $inventario = Inventario::firstOrCreate(
                    [
                        'producto_id' => $detalle->producto_id,
                        'almacen_id' => $almacenId,
                    ],
                    [
                        'owner_id' => $ownerId,
                        'cantidad' => 0,
                        'cantidad_minima' => 0,
                    ]
                );
                $inventario->increment('cantidad', $detalle->cantidad);
            }
        }

        if ($validated['estado'] === 'cancelada' && $estadoAnterior === 'recibida') {
            $compra->load('detalleCompras');
            foreach ($compra->detalleCompras as $detalle) {
                $inventario = Inventario::firstOrCreate(
                    [
                        'producto_id' => $detalle->producto_id,
                        'almacen_id' => $almacenId,
                    ],
                    [
                        'owner_id' => $ownerId,
                        'cantidad' => 0,
                        'cantidad_minima' => 0,
                    ]
                );
                $inventario->decrement('cantidad', $detalle->cantidad);
            }
        }

        return redirect()->route('compras.index');
    }

    public function destroy(Compra $compra): RedirectResponse
    {
        if ($compra->owner_id !== Auth::user()->getOwnerId()) {
            abort(403, 'No tienes permiso para eliminar esta compra.');
        }

        $compra->delete();

        return redirect()->route('compras.index');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Compra::with('proveedor')->where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('proveedor', fn ($pq) => $pq->where('nombre', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $compras = $query->orderBy('created_at', 'desc')->get();
        $filename = 'compras_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($compras) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['ID', 'Numero', 'Proveedor', 'Fecha', 'Total', 'Estado', 'Notas'], ';');

            foreach ($compras as $c) {
                fputcsv($file, [
                    $c->id,
                    $c->numero,
                    $c->proveedor?->nombre ?? 'N/A',
                    $c->fecha,
                    $c->total,
                    $c->estado,
                    $c->notas,
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportExcel(Request $request)
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Compra::with('proveedor')->where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhereHas('proveedor', fn ($pq) => $pq->where('nombre', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        return Excel::download(new ComprasExport($query->get()), 'compras_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt,xlsx,xls']);
        Excel::import(new ComprasImport, $request->file('archivo'));

        return redirect()->back()->with('success', 'Compras importadas correctamente.');
    }

    public function importExcel(Request $request): RedirectResponse
    {
        return $this->importCsv($request);
    }

    public function downloadPdf(Compra $compra)
    {
        $compra->load(['proveedor', 'detalleCompras.producto']);
        $logo = $this->resolveLogoPath();

        $pdf = Pdf::loadView('pdf.compra', compact('compra', 'logo'));

        return $pdf->download('compra_'.$compra->numero.'.pdf');
    }

    private function resolveLogoPath(): string
    {
        $user = auth()->user();

        if ($user && $user->business_logo_path) {
            $path = storage_path('app/public/'.$user->business_logo_path);
            if (file_exists($path)) {
                return $path;
            }
        }

        $settings = class_exists(WebSetting::class) ? WebSetting::getSettings() : null;

        return $settings && $settings->app_logo ? public_path($settings->app_logo) : public_path('favicon.svg');
    }
}
