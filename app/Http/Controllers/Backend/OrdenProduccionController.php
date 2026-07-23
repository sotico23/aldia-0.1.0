<?php

namespace App\Http\Controllers\Backend;

use App\Exports\OrdenProduccionExport;
use App\Http\Controllers\Controller;
use App\Imports\OrdenProduccionImport;
use App\Models\OrdenProduccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class OrdenProduccionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:mrp.produccion.create', only: ['create', 'store']),
            new Middleware('permission:mrp.produccion.edit', only: ['edit', 'update']),
            new Middleware('permission:mrp.produccion.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $ordenes = OrdenProduccion::orderBy('created_at', 'desc')->where('owner_id', Auth::user()->getOwnerId())->paginate(15);

        return Inertia::render('Backend/OrdenProduccion/Index', ['ordenes' => $ordenes]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'numero' => 'required|string|max:50|unique:ordenes_produccion,numero',
            'producto' => 'nullable|string|max:255',
            'cantidad' => 'nullable|integer|min:1',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'progreso' => 'nullable|integer|min:0|max:100',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
        ]);
        $validated['owner_id'] = Auth::user()->getOwnerId();
        OrdenProduccion::create($validated);

        return redirect()->route('ordenes-produccion.index');
    }

    public function update(Request $request, OrdenProduccion $ordenesProduccion): RedirectResponse
    {
        $validated = $request->validate([
            'numero' => 'nullable|string|max:50|unique:ordenes_produccion,numero,'.$ordenesProduccion->id,
            'producto' => 'nullable|string|max:255',
            'cantidad' => 'nullable|integer|min:1',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'progreso' => 'nullable|integer|min:0|max:100',
            'estado' => 'nullable|string|max:50',
            'notas' => 'nullable|string',
        ]);

        $updateData = [];
        foreach ($validated as $key => $value) {
            if ($value !== null) {
                $updateData[$key] = $value;
            }
        }

        if (! empty($updateData)) {
            $ordenesProduccion->update($updateData);
        }

        return redirect()->route('ordenes-produccion.index');
    }

    public function destroy(OrdenProduccion $ordenesProduccion): RedirectResponse
    {
        $ordenesProduccion->delete();

        return redirect()->route('ordenes-produccion.index');
    }

    public function exportCsv(Request $request)
    {
        return Excel::download(new OrdenProduccionExport, 'ordenes_produccion_'.now()->format('Ymd_His').'.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new OrdenProduccionExport, 'ordenes_produccion_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        try {
            Excel::import(new OrdenProduccionImport, $request->file('archivo'));

            return redirect()->back()->with('success', 'Órdenes de producción importadas correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al importar: '.$e->getMessage());
        }
    }

    public function importExcel(Request $request): RedirectResponse
    {
        return $this->importCsv($request);
    }
}
