<?php

namespace App\Http\Controllers\Backend;

use App\Exports\NominasExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\NominasImport;
use App\Models\Asistencia;
use App\Models\Empleado;
use App\Models\Nomina;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class NominaController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:rrhh.nominas.create', only: ['create', 'store']),
            new Middleware('permission:rrhh.nominas.edit', only: ['edit', 'update']),
            new Middleware('permission:rrhh.nominas.delete', only: ['destroy']),
            new Middleware('permission:rrhh.nominas.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:rrhh.nominas.import', only: ['importCsv', 'importExcel']),
        ];
    }

    protected function getExportClass(array $filters): object
    {
        return new NominasExport($filters);
    }

    protected function getImportClass(): object
    {
        return new NominasImport;
    }

    public function index(): Response
    {
        $nominas = Nomina::orderBy('periodo', 'desc')->paginate(15);

        return Inertia::render('Backend/Nominas/Index', ['nominas' => $nominas]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'periodo' => 'required|string|max:20',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'total_bruto' => 'nullable|numeric|min:0',
            'total_deducciones' => 'nullable|numeric|min:0',
            'total_neto' => 'nullable|numeric|min:0',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
            'detalles' => 'nullable|array',
        ]);
        $validated['owner_id'] = auth()->user()->getOwnerId();
        Nomina::create($validated);

        return redirect()->route('nominas.index');
    }

    public function update(Request $request, Nomina $nomina): RedirectResponse
    {
        $validated = $request->validate([
            'periodo' => 'required|string|max:20',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'total_bruto' => 'nullable|numeric|min:0',
            'total_deducciones' => 'nullable|numeric|min:0',
            'total_neto' => 'nullable|numeric|min:0',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
            'detalles' => 'nullable|array',
        ]);
        $nomina->update($validated);

        return redirect()->route('nominas.index');
    }

    public function calcularProporcional(Request $request)
    {
        $periodo = $request->input('periodo');
        $fechaInicio = $request->input('fecha_inicio');
        $fechaFin = $request->input('fecha_fin');

        if (! $periodo) {
            return response()->json([]);
        }

        $ownerId = auth()->user()->getOwnerId();
        $empleados = Empleado::activos()->where('owner_id', $ownerId)->get();
        $calculos = [];

        $asistenciasQuery = Asistencia::whereIn('empleado_id', $empleados->pluck('id'));

        if ($fechaInicio && $fechaFin) {
            $asistenciasQuery->whereBetween('fecha', [$fechaInicio, $fechaFin]);
        } else {
            $year = substr($periodo, 0, 4);
            $month = substr($periodo, 5, 2);
            $asistenciasQuery->whereYear('fecha', $year)->whereMonth('fecha', $month);
        }

        $asistenciasPorEmpleado = $asistenciasQuery->get()->groupBy('empleado_id');

        foreach ($empleados as $empleado) {
            $sueldoPactado = $empleado->sueldo_liquido_pactado ?? $empleado->salario ?? 0;
            $sueldoDiario = $sueldoPactado > 0 ? round($sueldoPactado / 30) : 0;

            // Consultar asistencias del mes ya precargadas
            $asistencias = $asistenciasPorEmpleado->get($empleado->id, collect());

            $diasAsistidos = $asistencias->where('estado', '!=', 'ausente')->count();

            $sueldoProporcional = $sueldoDiario * $diasAsistidos;

            $calculos[] = [
                'empleado_id' => $empleado->id,
                'nombre' => $empleado->nombre,
                'apellido' => $empleado->apellido,
                'rut' => $empleado->rut,
                'hora_entrada' => $empleado->hora_entrada?->format('H:i'),
                'hora_salida' => $empleado->hora_salida?->format('H:i'),
                'sueldo_pactado' => $sueldoPactado,
                'dias_asistidos' => $diasAsistidos,
                'sueldo_proporcional' => $sueldoProporcional,
            ];
        }

        return response()->json($calculos);
    }

    public function destroy(Nomina $nomina): RedirectResponse
    {
        $nomina->delete();

        return redirect()->route('nominas.index');
    }
}
