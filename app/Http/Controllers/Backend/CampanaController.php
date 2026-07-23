<?php

namespace App\Http\Controllers\Backend;

use App\Exports\CampanasExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\HasBulkOperations;
use App\Imports\CampanasImport;
use App\Models\Campana;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class CampanaController extends Controller implements HasMiddleware
{
    use HasBulkOperations;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:comercial.campanas.create', only: ['create', 'store']),
            new Middleware('permission:comercial.campanas.edit', only: ['edit', 'update']),
            new Middleware('permission:comercial.campanas.delete', only: ['destroy']),
            new Middleware('permission:comercial.campanas.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:comercial.campanas.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function getExportClass(array $filters): object
    {
        return new CampanasExport($filters);
    }

    public function getImportClass(): object
    {
        return new CampanasImport;
    }

    public function index(Request $request): Response
    {
        $ownerId = auth()->user()->getOwnerId();
        $query = Campana::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhere('canal', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado') && $request->input('estado') !== 'all') {
            $query->where('estado', $request->input('estado'));
        }

        $campanas = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Backend/Campanas/Index', [
            'campanas' => $campanas,
            'filters' => $request->only(['search', 'estado']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo' => 'nullable|string|max:100',
            'canal' => 'nullable|string|max:100',
            'objetivo' => 'nullable|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'presupuesto' => 'nullable|numeric|min:0',
            'presupuesto_real' => 'nullable|numeric|min:0',
            'visitas' => 'nullable|integer|min:0',
            'leads' => 'nullable|integer|min:0',
            'conversiones' => 'nullable|integer|min:0',
            'roi' => 'nullable|numeric',
            'estado' => 'required|string|max:50',
        ]);

        $validated['owner_id'] = auth()->user()->getOwnerId();
        Campana::create($validated);

        return redirect()->route('campanas.index')->with('success', 'Campaña creada correctamente.');
    }

    public function update(Request $request, Campana $campana): RedirectResponse
    {
        $this->authorizeOwner($campana);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'tipo' => 'nullable|string|max:100',
            'canal' => 'nullable|string|max:100',
            'objetivo' => 'nullable|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'presupuesto' => 'nullable|numeric|min:0',
            'presupuesto_real' => 'nullable|numeric|min:0',
            'visitas' => 'nullable|integer|min:0',
            'leads' => 'nullable|integer|min:0',
            'conversiones' => 'nullable|integer|min:0',
            'roi' => 'nullable|numeric',
            'estado' => 'required|string|max:50',
        ]);

        $campana->update($validated);

        return redirect()->route('campanas.index')->with('success', 'Campaña actualizada correctamente.');
    }

    public function destroy(Campana $campana): RedirectResponse
    {
        $this->authorizeOwner($campana);
        $campana->delete();

        return redirect()->route('campanas.index')->with('success', 'Campaña eliminada correctamente.');
    }

    protected function authorizeOwner(Campana $campana): void
    {
        if ($campana->owner_id !== auth()->user()->getOwnerId()) {
            abort(403);
        }
    }
}
