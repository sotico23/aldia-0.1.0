<?php

namespace App\Http\Controllers\Backend;

use App\Exports\EvaluacionesExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\EvaluacionesImport;
use App\Models\Evaluacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class EvaluacionController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:rrhh.evaluaciones.create', only: ['create', 'store']),
            new Middleware('permission:rrhh.evaluaciones.edit', only: ['edit', 'update']),
            new Middleware('permission:rrhh.evaluaciones.delete', only: ['destroy']),
            new Middleware('permission:rrhh.evaluaciones.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:rrhh.evaluaciones.import', only: ['importCsv', 'importExcel']),
        ];
    }

    protected function getExportClass(array $filters): object
    {
        return new EvaluacionesExport($filters);
    }

    protected function getImportClass(): object
    {
        return new EvaluacionesImport;
    }

    public function index(): Response
    {
        $evaluaciones = Evaluacion::orderBy('created_at', 'desc')->get();

        return Inertia::render('Backend/Evaluaciones/Index', ['evaluaciones' => $evaluaciones]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'empleado_id' => 'nullable|integer',
            'evaluador_id' => 'nullable|integer',
            'fecha' => 'nullable|date',
            'periodo' => 'nullable|string|max:100',
            'puntuacion' => 'nullable|numeric|min:0|max:100',
            'comentarios' => 'nullable|string',
            'tipo' => 'nullable|string|max:50',
            'estado' => 'required|string|max:50',
        ]);
        Evaluacion::create($validated);

        return redirect()->route('evaluaciones.index');
    }

    public function update(Request $request, Evaluacion $evaluacione): RedirectResponse
    {
        $validated = $request->validate([
            'empleado_id' => 'nullable|integer',
            'evaluador_id' => 'nullable|integer',
            'fecha' => 'nullable|date',
            'periodo' => 'nullable|string|max:100',
            'puntuacion' => 'nullable|numeric|min:0|max:100',
            'comentarios' => 'nullable|string',
            'tipo' => 'nullable|string|max:50',
            'estado' => 'required|string|max:50',
        ]);
        $evaluacione->update($validated);

        return redirect()->route('evaluaciones.index');
    }

    public function destroy(Evaluacion $evaluacione): RedirectResponse
    {
        $evaluacione->delete();

        return redirect()->route('evaluaciones.index');
    }
}
