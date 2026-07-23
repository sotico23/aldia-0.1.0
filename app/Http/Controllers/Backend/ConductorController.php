<?php

namespace App\Http\Controllers\Backend;

use App\Exports\ConductoresExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\ConductoresImport;
use App\Models\Conductor;
use App\Models\Empleado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ConductorController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:flota.conductores.create', only: ['create', 'store']),
            new Middleware('permission:flota.conductores.edit', only: ['edit', 'update']),
            new Middleware('permission:flota.conductores.delete', only: ['destroy']),
            new Middleware('permission:flota.conductores.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:flota.conductores.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(Request $request): Response
    {
        $query = Conductor::query();

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%")
                    ->orWhere('licencia', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where(fn ($q) => $q->where('estado', $request->input('estado')));
        }

        $conductores = $query->orderBy('nombre')->paginate(15)->withQueryString();

        return Inertia::render('Backend/Conductores/Index', [
            'conductores' => $conductores,
            'empleados' => Empleado::orderBy('nombre')->get(['id', 'nombre', 'apellido', 'rut', 'email', 'telefono']),
            'filters' => $request->only(['search', 'estado']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->has('rut') && $request->rut) {
            $request->merge(['rut' => strtoupper(trim($request->rut))]);
        }

        $validated = $request->validate([
            'empleado_id' => 'nullable|exists:empleados,id',
            'nombre' => 'required|string|max:255',
            'rut' => 'nullable|string|max:20|unique:conductores,rut',
            'licencia' => 'nullable|string|max:50',
            'telefono' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
        ]);

        try {
            Conductor::create($validated);

            return redirect()->route('conductores.index')->with('success', 'Conductor registrado exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al registrar el conductor: '.$e->getMessage());
        }
    }

    public function update(Request $request, Conductor $conductore): RedirectResponse
    {
        if ($request->filled('rut')) {
            $request->merge(['rut' => strtoupper(trim($request->rut))]);
        }

        $rules = [
            'empleado_id' => 'sometimes|nullable|exists:empleados,id',
            'nombre' => 'sometimes|string|max:255',
            'rut' => 'sometimes|nullable|string|max:20|unique:conductores,rut,'.$conductore->id,
            'licencia' => 'sometimes|nullable|string|max:50',
            'telefono' => 'sometimes|nullable|string|max:50',
            'email' => 'sometimes|nullable|email',
            'estado' => 'sometimes|nullable|string|in:activo,inactivo,licencia_vencida',
            'notas' => 'sometimes|nullable|string',
        ];

        $validated = $request->validate($rules);

        $toSave = array_map(fn ($v) => $v === '' ? null : $v, $validated);
        $toSave = array_filter($toSave, fn ($v) => $v !== null);

        if (! empty($toSave)) {
            try {
                $conductore->update($toSave);

                return redirect()->route('conductores.index')->with('success', 'Conductor actualizado exitosamente.');
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Error al actualizar el conductor: '.$e->getMessage());
            }
        }

        return redirect()->route('conductores.index');
    }

    public function destroy(Conductor $conductore): RedirectResponse
    {
        try {
            $conductore->delete();

            return redirect()->route('conductores.index')->with('success', 'Conductor eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'No se pudo eliminar el conductor: '.$e->getMessage());
        }
    }

    public function simularTracking(Conductor $conductor): RedirectResponse
    {
        $latitud = -33.4489 + (rand(-100, 100) / 1000);
        $longitud = -70.6693 + (rand(-100, 100) / 1000);

        $conductor->update([
            'lat' => $latitud,
            'lng' => $longitud,
            'ultima_actualizacion' => now(),
        ]);

        return redirect()->back()->with('success', 'Ubicacion simulate');
    }

    public function limpiarTracking(Conductor $conductor): RedirectResponse
    {
        $conductor->update([
            'lat' => null,
            'lng' => null,
            'ultima_actualizacion' => null,
        ]);

        return redirect()->back()->with('success', 'Ubicacion limpiada');
    }

    protected function getExportClass(array $filters): object
    {
        return new ConductoresExport($filters);
    }

    protected function getImportClass(): object
    {
        return new ConductoresImport;
    }
}
