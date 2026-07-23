<?php

namespace App\Http\Controllers\Backend;

use App\Exports\CotizacionesExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\FiltraPorCliente;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\CotizacionesImport;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\Producto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CotizacionController extends Controller implements HasMiddleware
{
    use FiltraPorCliente, HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:comercial.cotizaciones.create', only: ['create', 'store']),
            new Middleware('permission:comercial.cotizaciones.edit', only: ['edit', 'update']),
            new Middleware('permission:comercial.cotizaciones.delete', only: ['destroy']),
            new Middleware('permission:comercial.cotizaciones.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:comercial.cotizaciones.import', only: ['importCsv', 'importExcel']),
        ];
    }

    protected function getExportClass(array $filters): object
    {
        return new CotizacionesExport($filters);
    }

    protected function getImportClass(): object
    {
        return new CotizacionesImport;
    }

    public function index(Request $request): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Cotizacion::with('cliente')
            ->where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                    ->orWhere('notas', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('nombre', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('cliente_id') && $request->input('cliente_id') !== 'all') {
            $query->where('cliente_id', $request->input('cliente_id'));
        }

        if ($request->filled('estado') && $request->input('estado') !== 'all') {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('fechaDesde')) {
            $query->where('fecha', '>=', $request->input('fechaDesde'));
        }

        if ($request->filled('fechaHasta')) {
            $query->where('fecha', '<=', $request->input('fechaHasta'));
        }

        if ($this->usuarioEsCliente()) {
            $query->where('cliente_id', $this->getClienteAuth()->id);
        }

        $cotizaciones = $query->orderBy('fecha', 'desc')
            ->paginate(15)
            ->withQueryString();

        $clientes = Cliente::where('owner_id', $ownerId)->where('activo', true)->orderBy('nombre')->get();
        $productos = Producto::where('owner_id', $ownerId)->where('activo', true)->orderBy('nombre')->get();

        return Inertia::render('Backend/Cotizaciones/Index', [
            'cotizaciones' => $cotizaciones,
            'clientes' => $clientes,
            'productos' => $productos,
            'filters' => $request->only(['search', 'cliente_id', 'estado', 'fechaDesde', 'fechaHasta']),
            'owner_id' => $ownerId,
            'user_id' => auth()->id(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'numero' => 'required|string|max:20|unique:cotizaciones,numero',
            'cliente_id' => 'required|exists:clientes,id',
            'fecha' => 'required|date',
            'fecha_validez' => 'nullable|date',
            'subtotal' => 'nullable|numeric|min:0',
            'impuesto' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
            'detalles' => 'nullable|array',
            'condiciones' => 'nullable|string',
            'iva_personalizado' => 'nullable|numeric|min:0',
            'descuento_tipo' => 'nullable|string|in:monto,porcentaje',
            'descuento_monto' => 'nullable|numeric|min:0',
        ]);

        if (isset($validated['subtotal'])) {
            $validated['subtotal'] = (int) round($validated['subtotal']);
        }
        if (isset($validated['impuesto'])) {
            $validated['impuesto'] = (int) round($validated['impuesto']);
        }
        if (isset($validated['total'])) {
            $validated['total'] = (int) round($validated['total']);
        }
        if (isset($validated['descuento_monto'])) {
            $validated['descuento_monto'] = (int) round($validated['descuento_monto']);
        }
        if (isset($validated['detalles']) && is_array($validated['detalles'])) {
            foreach ($validated['detalles'] as &$detalle) {
                if (isset($detalle['cantidad'])) {
                    $detalle['cantidad'] = (int) $detalle['cantidad'];
                }
                if (isset($detalle['precio'])) {
                    $detalle['precio'] = (int) round($detalle['precio']);
                }
            }
        }

        $validated['user_id'] = auth()->id();
        $validated['owner_id'] = auth()->user()->getOwnerId();
        Cotizacion::create($validated);

        return redirect()->route('cotizaciones.index')->with('success', 'Cotización creada correctamente.');
    }

    public function update(Request $request, Cotizacion $cotizacion): RedirectResponse
    {
        $this->authorizeOwner($cotizacion);

        $validated = $request->validate([
            'numero' => [
                'required',
                'string',
                'max:20',
                Rule::unique('cotizaciones')->ignore($cotizacion->id),
            ],
            'cliente_id' => 'required|exists:clientes,id',
            'fecha' => 'required|date',
            'fecha_validez' => 'nullable|date',
            'subtotal' => 'nullable|numeric|min:0',
            'impuesto' => 'nullable|numeric|min:0',
            'total' => 'nullable|numeric|min:0',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
            'detalles' => 'nullable|array',
            'condiciones' => 'nullable|string',
            'iva_personalizado' => 'nullable|numeric|min:0',
            'descuento_tipo' => 'nullable|string|in:monto,porcentaje',
            'descuento_monto' => 'nullable|numeric|min:0',
        ]);

        if (isset($validated['subtotal'])) {
            $validated['subtotal'] = (int) round($validated['subtotal']);
        }
        if (isset($validated['impuesto'])) {
            $validated['impuesto'] = (int) round($validated['impuesto']);
        }
        if (isset($validated['total'])) {
            $validated['total'] = (int) round($validated['total']);
        }
        if (isset($validated['descuento_monto'])) {
            $validated['descuento_monto'] = (int) round($validated['descuento_monto']);
        }
        if (isset($validated['detalles']) && is_array($validated['detalles'])) {
            foreach ($validated['detalles'] as &$detalle) {
                if (isset($detalle['cantidad'])) {
                    $detalle['cantidad'] = (int) $detalle['cantidad'];
                }
                if (isset($detalle['precio'])) {
                    $detalle['precio'] = (int) round($detalle['precio']);
                }
            }
        }

        $cotizacion->update($validated);

        return redirect()->route('cotizaciones.index')->with('success', 'Cotización actualizada correctamente.');
    }

    public function destroy(Cotizacion $cotizacion): RedirectResponse
    {
        $this->authorizeOwner($cotizacion);
        $cotizacion->delete();

        return redirect()->route('cotizaciones.index')->with('success', 'Cotización eliminada correctamente.');
    }

    public function downloadPdf(Cotizacion $cotizacion)
    {
        $this->authorizeOwner($cotizacion);
        $cotizacion->load(['cliente', 'emisor']);
        $pdf = Pdf::loadView('pdf.cotizacion', compact('cotizacion'));

        return $pdf->download('cotizacion_'.$cotizacion->numero.'.pdf');
    }

    public function previewPdf(Cotizacion $cotizacion)
    {
        $this->authorizeOwner($cotizacion);
        $cotizacion->load(['cliente', 'emisor']);

        return view('pdf.cotizacion', compact('cotizacion'));
    }

    protected function authorizeOwner(Cotizacion $cotizacion): void
    {
        if ($cotizacion->owner_id !== auth()->user()->getOwnerId()) {
            abort(403);
        }
    }
}
