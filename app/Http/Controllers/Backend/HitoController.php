<?php

namespace App\Http\Controllers\Backend;

use App\Exports\HitosExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\HitosImport;
use App\Models\Hito;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class HitoController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:proyectos.hitos.create', only: ['create', 'store']),
            new Middleware('permission:proyectos.hitos.edit', only: ['edit', 'update']),
            new Middleware('permission:proyectos.hitos.delete', only: ['destroy']),
            new Middleware('permission:proyectos.hitos.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:proyectos.hitos.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $hitos = Hito::where('owner_id', $ownerId)->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Backend/Hitos/Index', ['hitos' => $hitos]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'proyecto_id' => 'nullable|integer',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_prevista' => 'nullable|date',
            'fecha_real' => 'nullable|date',
            'estado' => 'required|string|max:50',
            'responsable_id' => 'nullable|integer',
        ]);
        Hito::create($validated);

        return redirect()->route('hitos.index');
    }

    public function update(Request $request, Hito $hito): RedirectResponse
    {
        $validated = $request->validate([
            'proyecto_id' => 'nullable|integer',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_prevista' => 'nullable|date',
            'fecha_real' => 'nullable|date',
            'estado' => 'required|string|max:50',
            'responsable_id' => 'nullable|integer',
        ]);
        $hito->update($validated);

        return redirect()->route('hitos.index');
    }

    public function destroy(Hito $hito): RedirectResponse
    {
        $hito->delete();

        return redirect()->route('hitos.index');
    }

    protected function getExportClass(array $filters): object
    {
        return new HitosExport($filters);
    }

    protected function getImportClass(): object
    {
        return new HitosImport;
    }
}
