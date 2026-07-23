<?php

namespace App\Http\Controllers\Backend;

use App\Exports\CalidadExport;
use App\Http\Controllers\Controller;
use App\Imports\CalidadImport;
use App\Models\ControlCalidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ControlCalidadController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:mrp.calidad.create', only: ['create', 'store']),
            new Middleware('permission:mrp.calidad.edit', only: ['edit', 'update']),
            new Middleware('permission:mrp.calidad.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $controles = ControlCalidad::orderBy('fecha', 'desc')->where('owner_id', Auth::user()->getOwnerId())->paginate(15);

        return Inertia::render('Backend/Calidad/Index', ['controles' => $controles]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lote' => 'nullable|string|max:100',
            'producto' => 'nullable|string|max:255',
            'tipo' => 'nullable|string|max:100',
            'resultado' => 'nullable|string|max:50',
            'cantidad_muestra' => 'nullable|integer|min:0',
            'cantidad_defectuosa' => 'nullable|integer|min:0',
            'observaciones' => 'nullable|string',
            'fecha' => 'nullable|date',
        ]);
        $validated['owner_id'] = Auth::user()->getOwnerId();
        ControlCalidad::create($validated);

        return redirect()->route('calidad.index');
    }

    public function update(Request $request, ControlCalidad $calidad): RedirectResponse
    {
        $validated = $request->validate([
            'lote' => 'nullable|string|max:100',
            'producto' => 'nullable|string|max:255',
            'tipo' => 'nullable|string|max:100',
            'resultado' => 'nullable|string|max:50',
            'cantidad_muestra' => 'nullable|integer|min:0',
            'cantidad_defectuosa' => 'nullable|integer|min:0',
            'observaciones' => 'nullable|string',
            'fecha' => 'nullable|date',
        ]);
        $calidad->update($validated);

        return redirect()->route('calidad.index');
    }

    public function destroy(ControlCalidad $calidad): RedirectResponse
    {
        $calidad->delete();

        return redirect()->route('calidad.index');
    }

    public function exportCsv(Request $request)
    {
        return Excel::download(new CalidadExport, 'control_calidad_'.now()->format('Ymd_His').'.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(new CalidadExport, 'control_calidad_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        try {
            Excel::import(new CalidadImport, $request->file('archivo'));

            return redirect()->back()->with('success', 'Registros de calidad importados correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al importar: '.$e->getMessage());
        }
    }

    public function importExcel(Request $request): RedirectResponse
    {
        return $this->importCsv($request);
    }
}
