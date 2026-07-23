<?php

namespace App\Http\Controllers\Backend;

use App\Exports\ImpuestosExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\ImpuestosImport;
use App\Models\Impuesto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ImpuestoController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:finanzas.impuestos.create', only: ['create', 'store']),
            new Middleware('permission:finanzas.impuestos.edit', only: ['edit', 'update']),
            new Middleware('permission:finanzas.impuestos.delete', only: ['destroy']),
            new Middleware('permission:finanzas.impuestos.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:finanzas.impuestos.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(): Response
    {
        $impuestos = Impuesto::orderBy('created_at', 'desc')->paginate(15);

        $impuestosAll = Impuesto::all();

        $tasaPromedio = $impuestosAll->avg('tasa') ?? 0;

        $porTipo = $impuestosAll->groupBy('tipo')->map(function ($group) {
            return $group->count();
        })->toArray();

        $porEstado = $impuestosAll->groupBy('estado')->map(function ($group) {
            return $group->count();
        })->toArray();

        $impuestosData = $impuestosAll->map(function ($i) {
            return [
                'nombre' => $i->nombre,
                'tasa' => (float) $i->tasa,
                'tipo' => $i->tipo,
            ];
        })->toArray();

        $impuestosCount = $impuestosAll->count();
        $activosCount = $impuestosAll->where('estado', 'activo')->count();

        return Inertia::render('Backend/Impuestos/Index', [
            'impuestos' => $impuestos,
            'chartData' => [
                'tasaPromedio' => round($tasaPromedio, 2),
                'porTipo' => $porTipo,
                'porEstado' => $porEstado,
                'impuestosList' => $impuestosData,
                'totalImpuestos' => $impuestosCount,
                'activos' => $activosCount,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50',
            'tasa' => 'required|numeric|min:0|max:100',
            'tipo' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'estado' => 'required|string|max:50',
        ]);
        Impuesto::create($validated);

        return redirect()->route('impuestos.index');
    }

    public function update(Request $request, Impuesto $impuesto): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50',
            'tasa' => 'required|numeric|min:0|max:100',
            'tipo' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'estado' => 'required|string|max:50',
        ]);
        $impuesto->update($validated);

        return redirect()->route('impuestos.index');
    }

    public function destroy(Impuesto $impuesto): RedirectResponse
    {
        $impuesto->delete();

        return redirect()->route('impuestos.index');
    }

    protected function getExportClass(array $filters): object
    {
        return new ImpuestosExport($filters);
    }

    protected function getImportClass(): object
    {
        return new ImpuestosImport;
    }
}
