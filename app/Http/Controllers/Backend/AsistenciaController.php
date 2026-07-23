<?php

namespace App\Http\Controllers\Backend;

use App\Exports\AsistenciasExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Models\Almacen;
use App\Models\Asistencia;
use App\Models\Empleado;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class AsistenciaController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:rrhh.asistencia.create', only: ['create', 'store']),
            new Middleware('permission:rrhh.asistencia.edit', only: ['edit', 'update']),
            new Middleware('permission:rrhh.asistencia.delete', only: ['destroy']),
            new Middleware('permission:rrhh.asistencia.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:rrhh.asistencia.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = auth()->user()->getOwnerId();

        $query = Asistencia::with('empleado.almacen')
            ->where('owner_id', $ownerId);

        if ($request->filled('empleado_id')) {
            $query->where('empleado_id', $request->input('empleado_id'));
        }

        if ($request->filled('estado') && $request->input('estado') !== 'all') {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->input('fecha_hasta'));
        }

        if ($request->filled('almacen_id')) {
            $query->whereHas('empleado', function ($q) use ($request) {
                $q->where('almacen_id', $request->input('almacen_id'));
            });
        }

        $asistencias = $query->orderBy('fecha', 'desc')
            ->paginate(15)
            ->withQueryString();

        $selectedEmpleadoId = $request->input('stats_empleado_id');
        $selectedMes = $request->input('stats_mes', now()->format('Y-m'));
        $statsAlmacenId = $request->input('stats_almacen');

        $statsAsistencias = [];
        if ($selectedEmpleadoId) {
            $statsAsistencias = Asistencia::where('owner_id', $ownerId)
                ->where('empleado_id', $selectedEmpleadoId)
                ->whereYear('fecha', substr($selectedMes, 0, 4))
                ->whereMonth('fecha', substr($selectedMes, 5, 2))
                ->get();
        } elseif ($statsAlmacenId) {
            $statsAsistencias = Asistencia::where('owner_id', $ownerId)
                ->whereHas('empleado', function ($q) use ($statsAlmacenId) {
                    $q->where('almacen_id', $statsAlmacenId);
                })
                ->whereYear('fecha', substr($selectedMes, 0, 4))
                ->whereMonth('fecha', substr($selectedMes, 5, 2))
                ->get();
        }

        $empleados = Empleado::with('almacen')
            ->where('owner_id', $ownerId)
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'apellido', 'rut', 'cargo', 'departamento', 'almacen_id', 'hora_entrada', 'hora_salida']);

        $almacenes = Almacen::where('owner_id', $ownerId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Backend/Asistencia/Index', [
            'asistencias' => $asistencias,
            'empleados' => $empleados,
            'almacenes' => $almacenes,
            'statsAsistencias' => $statsAsistencias,
            'filters' => $request->only([
                'empleado_id', 'estado', 'fecha_desde', 'fecha_hasta',
                'stats_empleado_id', 'stats_mes', 'almacen_id', 'stats_almacen',
            ]),
        ]);
    }

    protected function getExportClass(array $filters): object
    {
        return new AsistenciasExport($filters);
    }

    protected function getImportClass(): object
    {
        return new AsistenciasImport;
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'empleado_id' => 'nullable|integer',
            'fecha' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha',
            'hora_entrada' => 'nullable|string|max:10',
            'hora_salida' => 'nullable|string|max:10',
            'horas_trabajadas' => 'nullable|numeric|min:0',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
        ]);

        $ownerId = auth()->user()->getOwnerId();

        $inicio = Carbon::parse($validated['fecha']);
        $fin = isset($validated['fecha_fin']) ? Carbon::parse($validated['fecha_fin']) : $inicio;

        $created = 0;
        $skipped = 0;

        for ($date = $inicio->copy(); $date->lte($fin); $date->addDay()) {
            $exists = Asistencia::where('owner_id', $ownerId)
                ->where('empleado_id', $validated['empleado_id'])
                ->where('fecha', $date->format('Y-m-d'))
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            Asistencia::create([
                'empleado_id' => $validated['empleado_id'],
                'owner_id' => $ownerId,
                'fecha' => $date->format('Y-m-d'),
                'hora_entrada' => $validated['hora_entrada'] ?? null,
                'hora_salida' => $validated['hora_salida'] ?? null,
                'horas_trabajadas' => $validated['horas_trabajadas'] ?? null,
                'estado' => $validated['estado'],
                'notas' => $validated['notas'] ?? null,
            ]);

            $created++;
        }

        $message = "$created registro(s) creado(s)";
        if ($skipped > 0) {
            $message .= ", $skipped omitido(s) (ya existían)";
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, Asistencia $asistencia): RedirectResponse
    {
        $validated = $request->validate([
            'empleado_id' => 'nullable|integer',
            'fecha' => 'nullable|date',
            'hora_entrada' => 'nullable|string|max:10',
            'hora_salida' => 'nullable|string|max:10',
            'horas_trabajadas' => 'nullable|numeric|min:0',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
        ]);
        $asistencia->update($validated);

        return back();
    }

    public function destroy(Asistencia $asistencia): RedirectResponse
    {
        $asistencia->delete();

        return back();
    }
}
