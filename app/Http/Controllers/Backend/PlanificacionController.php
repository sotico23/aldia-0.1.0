<?php

namespace App\Http\Controllers\Backend;

use App\Exports\PlanificacionExport;
use App\Http\Controllers\Controller;
use App\Imports\PlanificacionImport;
use App\Models\Planificacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class PlanificacionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:mrp.planificacion.create', only: ['create', 'store']),
            new Middleware('permission:mrp.planificacion.edit', only: ['edit', 'update']),
            new Middleware('permission:mrp.planificacion.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $planificaciones = Planificacion::orderBy('created_at', 'desc')->where('owner_id', Auth::user()->getOwnerId())->paginate(15);

        return Inertia::render('Backend/Planificacion/Index', ['planificaciones' => $planificaciones]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'proyecto_id' => 'nullable|integer|exists:proyectos,id',
            'responsable_id' => 'nullable|integer|exists:users,id',
            'estado' => 'required|string|max:50',
            'prioridad' => 'nullable|string|max:50',
            'ubicacion' => 'nullable|string|max:255',
            'presupuesto' => 'nullable|numeric|min:0',
            'categoria' => 'nullable|string|max:100',
            'objetivo' => 'nullable|string',
            'asistentes_max' => 'nullable|integer|min:1',
            'fecha_limite' => 'nullable|date',
            'requiere_materiales' => 'nullable|boolean',
            'materiales' => 'nullable|string',
            'proveedor_id' => 'nullable|integer',
            'contacto_emergencia' => 'nullable|string|max:255',
            'telefono_emergencia' => 'nullable|string|max:20',
        ]);
        $validated['owner_id'] = Auth::user()->getOwnerId();
        Planificacion::create($validated);

        return redirect()->route('planificacion.index');
    }

    public function update(Request $request, Planificacion $planificacion): RedirectResponse
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'proyecto_id' => 'nullable|integer|exists:proyectos,id',
            'responsable_id' => 'nullable|integer|exists:users,id',
            'estado' => 'required|string|max:50',
            'prioridad' => 'nullable|string|max:50',
            'ubicacion' => 'nullable|string|max:255',
            'presupuesto' => 'nullable|numeric|min:0',
            'categoria' => 'nullable|string|max:100',
            'objetivo' => 'nullable|string',
            'asistentes_max' => 'nullable|integer|min:1',
            'fecha_limite' => 'nullable|date',
            'requiere_materiales' => 'nullable|boolean',
            'materiales' => 'nullable|string',
            'proveedor_id' => 'nullable|integer',
            'contacto_emergencia' => 'nullable|string|max:255',
            'telefono_emergencia' => 'nullable|string|max:20',
        ]);
        $planificacion->update($validated);

        return redirect()->route('planificacion.index');
    }

    public function destroy(Planificacion $planificacion): RedirectResponse
    {
        $planificacion->delete();

        return redirect()->route('planificacion.index');
    }

    public function exportCsv(Request $request)
    {
        return Excel::download(new PlanificacionExport, 'planificacion_'.now()->format('Ymd_His').'.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new PlanificacionExport, 'planificacion_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        try {
            Excel::import(new PlanificacionImport, $request->file('archivo'));

            return redirect()->back()->with('success', 'Planificaciones importadas correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al importar: '.$e->getMessage());
        }
    }

    public function importExcel(Request $request): RedirectResponse
    {
        return $this->importCsv($request);
    }
}
