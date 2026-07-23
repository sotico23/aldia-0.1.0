<?php

namespace App\Http\Controllers\Backend;

use App\Exports\ContabilidadExport;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\ContabilidadImport;
use App\Models\Asiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ContabilidadController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:finanzas.contabilidad.create', only: ['create', 'store']),
            new Middleware('permission:finanzas.contabilidad.edit', only: ['edit', 'update']),
            new Middleware('permission:finanzas.contabilidad.delete', only: ['destroy']),
            new Middleware('permission:finanzas.contabilidad.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:finanzas.contabilidad.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(): Response
    {
        $asientos = Asiento::with('detalles')->orderBy('fecha', 'desc')->orderBy('numero', 'desc')->paginate(15);

        $datosGrafico = Asiento::selectRaw("
            DATE_FORMAT(fecha, '%Y-%m') as mes,
            SUM(total_debe) as total_debe,
            SUM(total_haber) as total_haber
        ")
            ->where('fecha', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        $mesesNombres = [
            '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic',
        ];

        $chartData = $datosGrafico->map(function ($item) {
            $mes = substr($item->mes, -2);

            return [
                'mes' => $mesNombres[$mes] ?? $item->mes,
                'debe' => (float) $item->total_debe,
                'haber' => (float) $item->total_haber,
            ];
        });

        $totalesTipo = Asiento::selectRaw('tipo, SUM(total_debe) as total')
            ->where('fecha', '>=', now()->subMonths(12)->startOfMonth())
            ->groupBy('tipo')
            ->get()
            ->map(fn ($i) => ['tipo' => ucfirst($i->tipo), 'total' => (float) $i->total]);

        $ultimos12Meses = $chartData->pluck('debe')->toArray();
        $promedio = count($ultimos12Meses) > 0 ? array_sum($ultimos12Meses) / count($ultimos12Meses) : 0;
        $tendencia = count($ultimos12Meses) >= 2
            ? ($ultimos12Meses[count($ultimos12Meses) - 1] - $ultimos12Meses[0]) / max(count($ultimos12Meses) - 1, 1)
            : 0;

        $proyeccion = [];
        for ($i = 1; $i <= 6; $i++) {
            $proyeccion[] = $promedio + ($tendencia * (count($ultimos12Meses) + $i));
        }

        return Inertia::render('Backend/Contabilidad/Index', [
            'asientos' => $asientos,
            'chartData' => $chartData,
            'totalesTipo' => $totalesTipo,
            'proyeccion' => $proyeccion,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'fecha' => 'required|date',
            'numero' => 'required|string|max:20|unique:asientos,numero',
            'descripcion' => 'required|string|max:255',
            'tipo' => 'required|string|max:20',
            'detalles' => 'required|array|min:1',
            'detalles.*.cuenta' => 'required|string|max:100',
            'detalles.*.cuenta_codigo' => 'required|string|max:20',
            'detalles.*.descripcion' => 'nullable|string',
            'detalles.*.debe' => 'required|numeric|min:0',
            'detalles.*.haber' => 'required|numeric|min:0',
        ]);

        $totalDebe = collect($validated['detalles'])->sum('debe');
        $totalHaber = collect($validated['detalles'])->sum('haber');

        if (bccomp((string) $totalDebe, (string) $totalHaber, 2) !== 0) {
            return back()->withErrors(['detalles' => 'El total del debe debe ser igual al total del haber']);
        }

        $asiento = Asiento::create([
            'fecha' => $validated['fecha'],
            'numero' => $validated['numero'],
            'descripcion' => $validated['descripcion'],
            'tipo' => $validated['tipo'],
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'estado' => true,
        ]);

        foreach ($validated['detalles'] as $detalle) {
            $asiento->detalles()->create($detalle);
        }

        return redirect()->route('contabilidad.index');
    }

    public function update(Request $request, Asiento $asiento): RedirectResponse
    {
        $validated = $request->validate([
            'fecha' => 'nullable|date',
            'numero' => 'nullable|string|max:20|unique:asientos,numero,'.$asiento->id,
            'descripcion' => 'nullable|string|max:255',
            'tipo' => 'nullable|string|max:20',
            'estado' => 'nullable|boolean',
        ]);

        $updateData = [];
        foreach ($validated as $key => $value) {
            if ($value !== null) {
                $updateData[$key] = $value;
            }
        }

        if (! empty($updateData)) {
            $asiento->update($updateData);
        }

        return redirect()->route('contabilidad.index');
    }

    public function destroy(Asiento $asiento): RedirectResponse
    {
        $asiento->delete();

        return redirect()->route('contabilidad.index');
    }

    protected function getExportClass(array $filters): object
    {
        return new ContabilidadExport($filters);
    }

    protected function getImportClass(): object
    {
        return new ContabilidadImport;
    }
}
