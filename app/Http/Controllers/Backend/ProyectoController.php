<?php

namespace App\Http\Controllers\Backend;

use App\Exports\ProyectosExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\ProyectosImport;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ProyectoController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:proyectos.proyectos.create', only: ['create', 'store']),
            new Middleware('permission:proyectos.proyectos.edit', only: ['edit', 'update']),
            new Middleware('permission:proyectos.proyectos.delete', only: ['destroy']),
            new Middleware('permission:proyectos.proyectos.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:proyectos.proyectos.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $proyectos = Proyecto::where('owner_id', $ownerId)->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Backend/Proyectos/Index', ['proyectos' => $proyectos]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'cliente' => 'nullable|string|max:255',
            'responsable' => 'nullable|string|max:255',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'presupuesto' => 'nullable|numeric|min:0',
            'gasto_real' => 'nullable|numeric|min:0',
            'progreso' => 'nullable|integer|min:0|max:100',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
        ]);
        Proyecto::create($validated);

        return redirect()->route('proyectos.index');
    }

    public function update(Request $request, Proyecto $proyecto): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'cliente' => 'nullable|string|max:255',
            'responsable' => 'nullable|string|max:255',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'presupuesto' => 'nullable|numeric|min:0',
            'gasto_real' => 'nullable|numeric|min:0',
            'progreso' => 'nullable|integer|min:0|max:100',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
        ]);
        $proyecto->update($validated);

        return redirect()->route('proyectos.index');
    }

    public function destroy(Proyecto $proyecto): RedirectResponse
    {
        $proyecto->delete();

        return redirect()->route('proyectos.index');
    }

    protected function getExportClass(array $filters): object
    {
        return new ProyectosExport($filters);
    }

    protected function getImportClass(): object
    {
        return new ProyectosImport;
    }
}
