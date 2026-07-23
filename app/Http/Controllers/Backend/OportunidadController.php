<?php

namespace App\Http\Controllers\Backend;

use App\Exports\OportunidadesExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\OportunidadesImport;
use App\Models\Cliente;
use App\Models\Oportunidad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class OportunidadController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:comercial.oportunidades.create', only: ['create', 'store']),
            new Middleware('permission:comercial.oportunidades.edit', only: ['edit', 'update']),
            new Middleware('permission:comercial.oportunidades.delete', only: ['destroy']),
            new Middleware('permission:comercial.oportunidades.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:comercial.oportunidades.import', only: ['importCsv', 'importExcel']),
        ];
    }

    protected function getExportClass(array $filters): object
    {
        return new OportunidadesExport($filters);
    }

    protected function getImportClass(): object
    {
        return new OportunidadesImport;
    }

    public function index(Request $request): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Oportunidad::with('cliente')
            ->where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($q2) use ($search) {
                        $q2->where('nombre', 'like', "%{$search}%");
                    })
                    ->orWhere('descripcion', 'like', "%{$search}%");
            });
        }

        if ($request->filled('cliente_id') && $request->input('cliente_id') !== 'all') {
            $query->where('cliente_id', $request->input('cliente_id'));
        }

        if ($request->filled('etapa') && $request->input('etapa') !== 'all') {
            $query->where('etapa', $request->input('etapa'));
        }

        if ($request->filled('fechaDesde')) {
            $query->where('fecha_cierre_estimada', '>=', $request->input('fechaDesde'));
        }

        if ($request->filled('fechaHasta')) {
            $query->where('fecha_cierre_estimada', '<=', $request->input('fechaHasta'));
        }

        if ($request->input('view') === 'kanban') {
            $oportunidades = [
                'data' => $query->orderBy('created_at', 'desc')->get(),
                'links' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1],
            ];
        } else {
            $oportunidades = $query->orderBy('created_at', 'desc')
                ->paginate(15)
                ->withQueryString();
        }

        $clientes = Cliente::where('activo', true)
            ->where('owner_id', $ownerId)
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Backend/Oportunidades/Index', [
            'oportunidades' => $oportunidades,
            'clientes' => $clientes,
            'filters' => $request->only(['search', 'cliente_id', 'etapa', 'fechaDesde', 'fechaHasta', 'view']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'cliente_id' => 'required|exists:clientes,id',
            'valor' => 'nullable|numeric|min:0',
            'etapa' => 'required|string|max:50',
            'probabilidad' => 'nullable|integer|min:0|max:100',
            'fecha_cierre_estimada' => 'nullable|date',
            'descripcion' => 'nullable|string',
        ]);

        if (isset($validated['valor'])) {
            $validated['valor'] = round($validated['valor']);
        }

        $validated['owner_id'] = auth()->user()->getOwnerId();
        Oportunidad::create($validated);

        return redirect()->route('oportunidades.index')->with('success', 'Oportunidad creada correctamente.');
    }

    public function update(Request $request, Oportunidad $oportunidad): RedirectResponse
    {
        $this->authorizeOwner($oportunidad);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'cliente_id' => 'required|exists:clientes,id',
            'valor' => 'nullable|numeric|min:0',
            'etapa' => 'required|string|max:50',
            'probabilidad' => 'nullable|integer|min:0|max:100',
            'fecha_cierre_estimada' => 'nullable|date',
            'descripcion' => 'nullable|string',
        ]);

        if (isset($validated['valor'])) {
            $validated['valor'] = round($validated['valor']);
        }

        $oportunidad->update($validated);

        return redirect()->route('oportunidades.index')->with('success', 'Oportunidad actualizada correctamente.');
    }

    public function destroy(Oportunidad $oportunidad): RedirectResponse
    {
        $this->authorizeOwner($oportunidad);
        $oportunidad->delete();

        return redirect()->route('oportunidades.index')->with('success', 'Oportunidad eliminada correctamente.');
    }

    public function show(Oportunidad $oportunidad): Response
    {
        $this->authorizeOwner($oportunidad);
        $oportunidad->load('cliente');

        return Inertia::render('Backend/Oportunidades/Show', [
            'oportunidad' => $oportunidad,
        ]);
    }

    public function updateEtapa(Request $request, Oportunidad $oportunidad)
    {
        $this->authorizeOwner($oportunidad);

        $validated = $request->validate([
            'etapa' => 'required|string|max:50',
        ]);

        $oportunidad->update(['etapa' => $validated['etapa']]);

        return back()->with('success', 'Etapa actualizada correctamente');
    }

    protected function authorizeOwner(Oportunidad $oportunidad): void
    {
        if ($oportunidad->owner_id !== auth()->user()->getOwnerId()) {
            abort(403);
        }
    }
}
