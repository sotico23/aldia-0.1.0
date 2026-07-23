<?php

namespace App\Http\Controllers\Backend;

use App\Exports\PagosExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\PagosImport;
use App\Models\Pago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class PagoController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:finanzas.pagos.create', only: ['create', 'store']),
            new Middleware('permission:finanzas.pagos.edit', only: ['edit', 'update']),
            new Middleware('permission:finanzas.pagos.delete', only: ['destroy']),
            new Middleware('permission:finanzas.pagos.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:finanzas.pagos.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(): Response
    {
        $pagos = Pago::orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Backend/Pagos/Index', ['pagos' => $pagos]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'factura_id' => 'nullable|integer',
            'proveedor_id' => 'nullable|integer',
            'monto' => 'required|numeric|min:0',
            'fecha_pago' => 'nullable|date',
            'metodo_pago' => 'nullable|string|max:100',
            'referencia' => 'nullable|string|max:255',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
        ]);
        Pago::create($validated);

        return redirect()->route('pagos.index');
    }

    public function update(Request $request, Pago $pago): RedirectResponse
    {
        $validated = $request->validate([
            'factura_id' => 'nullable|integer',
            'proveedor_id' => 'nullable|integer',
            'monto' => 'required|numeric|min:0',
            'fecha_pago' => 'nullable|date',
            'metodo_pago' => 'nullable|string|max:100',
            'referencia' => 'nullable|string|max:255',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
        ]);
        $pago->update($validated);

        return redirect()->route('pagos.index');
    }

    public function destroy(Pago $pago): RedirectResponse
    {
        $pago->delete();

        return redirect()->route('pagos.index');
    }

    protected function getExportClass(array $filters): object
    {
        return new PagosExport($filters);
    }

    protected function getImportClass(): object
    {
        return new PagosImport;
    }
}
