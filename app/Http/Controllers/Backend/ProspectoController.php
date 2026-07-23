<?php

namespace App\Http\Controllers\Backend;

use App\Exports\ProspectosExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\ProspectosImport;
use App\Models\Cliente;
use App\Models\Prospecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ProspectoController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:comercial.prospectos.create', only: ['create', 'store']),
            new Middleware('permission:comercial.prospectos.edit', only: ['edit', 'update']),
            new Middleware('permission:comercial.prospectos.delete', only: ['destroy']),
            new Middleware('permission:comercial.prospectos.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:comercial.prospectos.import', only: ['importCsv', 'importExcel']),
        ];
    }

    protected function getExportClass(array $filters): object
    {
        return new ProspectosExport($filters);
    }

    protected function getImportClass(): object
    {
        return new ProspectosImport;
    }

    public function index(Request $request): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Prospecto::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('empresa', 'like', "%{$search}%")
                    ->orWhere('rut', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado') && $request->input('estado') !== 'all') {
            $query->where('estado', $request->input('estado'));
        }

        if ($request->filled('fuente') && $request->input('fuente') !== 'all') {
            $query->where('fuente', $request->input('fuente'));
        }

        $prospectos = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Backend/Prospectos/Index', [
            'prospectos' => $prospectos,
            'filters' => $request->only(['search', 'estado', 'fuente']),
        ]);
    }

    public function show(Prospecto $prospecto): Response
    {
        return Inertia::render('Backend/Prospectos/Show', [
            'prospecto' => $prospecto,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'rut' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:50',
            'empresa' => 'nullable|string|max:255',
            'cargo' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'fuente' => 'nullable|string|max:100',
            'estado' => 'required|string|max:50',
            'prioridad' => 'nullable|string|max:20',
            'valor_estimado' => 'nullable|numeric|min:0',
            'fecha_seguimiento' => 'nullable|date',
            'notas' => 'nullable|string',
            'giro' => 'nullable|string|max:255',
        ]);

        $validated['owner_id'] = auth()->user()->getOwnerId();
        Prospecto::create($validated);

        return redirect()->route('prospectos.index')->with('success', 'Prospecto creado correctamente.');
    }

    public function update(Request $request, Prospecto $prospecto): RedirectResponse
    {
        $this->authorizeOwner($prospecto);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'rut' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:50',
            'empresa' => 'nullable|string|max:255',
            'cargo' => 'nullable|string|max:255',
            'direccion' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'fuente' => 'nullable|string|max:100',
            'estado' => 'required|string|max:50',
            'prioridad' => 'nullable|string|max:20',
            'valor_estimado' => 'nullable|numeric|min:0',
            'fecha_seguimiento' => 'nullable|date',
            'notas' => 'nullable|string',
            'giro' => 'nullable|string|max:255',
        ]);

        $prospecto->update($validated);

        return redirect()->route('prospectos.index')->with('success', 'Prospecto actualizado correctamente.');
    }

    public function updateEstado(Request $request, Prospecto $prospecto): RedirectResponse
    {
        $this->authorizeOwner($prospecto);

        $validated = $request->validate([
            'estado' => 'required|string|max:50',
        ]);
        $prospecto->update($validated);

        return redirect()->route('prospectos.index')->with('success', 'Estado de prospecto actualizado.');
    }

    public function destroy(Prospecto $prospecto): RedirectResponse
    {
        $this->authorizeOwner($prospecto);
        $prospecto->delete();

        return redirect()->route('prospectos.index')->with('success', 'Prospecto eliminado correctamente.');
    }

    protected function authorizeOwner(Prospecto $prospecto): void
    {
        if ($prospecto->owner_id !== auth()->user()->getOwnerId()) {
            abort(403);
        }
    }

    /**
     * Convierte un prospecto en un cliente real.
     */
    public function convertir(Request $request, Prospecto $prospecto): RedirectResponse
    {
        Cliente::create([
            'owner_id' => $prospecto->owner_id,
            'nombre' => $prospecto->nombre,
            'rut' => $prospecto->rut,
            'giro' => $prospecto->giro,
            'direccion' => $prospecto->direccion,
            'comuna' => $prospecto->comuna,
            'region' => $prospecto->region,
            'email' => $prospecto->email,
            'telefono' => $prospecto->telefono,
            'contacto' => $prospecto->nombre, // Asumimos el mismo para el inicio
            'notas' => 'Convertido desde prospecto. Notas previas: '.$prospecto->notas.' - Desc: '.$prospecto->descripcion,
            'activo' => true,
        ]);

        // Actualizamos estado o eliminamos. Lo marcaremos como convertido.
        $prospecto->update(['estado' => 'convertido']);

        return redirect()->route('clientes.index')
            ->with('success', '¡Prospecto convertido en cliente exitosamente!');
    }
}
