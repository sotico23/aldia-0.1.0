<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Bom;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BomController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:mrp.boms.create', only: ['create', 'store']),
            new Middleware('permission:mrp.boms.edit', only: ['edit', 'update']),
            new Middleware('permission:mrp.boms.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $boms = Bom::orderBy('nombre')->where('owner_id', Auth::user()->getOwnerId())->paginate(15);

        return Inertia::render('Backend/Boms/Index', ['boms' => $boms]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'producto_final' => 'nullable|string|max:255',
            'cantidad' => 'nullable|integer|min:1',
            'materiales' => 'nullable|array',
            'materiales.*.nombre' => 'required|string|max:255',
            'materiales.*.cantidad' => 'required|numeric|min:0',
            'materiales.*.unidad' => 'nullable|string|max:50',
            'materiales.*.costo_unitario' => 'nullable|numeric|min:0',
            'materiales.*.costo_total' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
            'notas' => 'nullable|string',
            'tipo' => 'nullable|string|in:bom,recipe,kit,formula,custom|max:50',
        ]);
        $validated['owner_id'] = Auth::user()->getOwnerId();
        Bom::create($validated);

        return redirect()->route('boms.index');
    }

    public function update(Request $request, Bom $bom): RedirectResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'producto_final' => 'nullable|string|max:255',
            'cantidad' => 'nullable|integer|min:1',
            'materiales' => 'nullable|array',
            'materiales.*.nombre' => 'required|string|max:255',
            'materiales.*.cantidad' => 'required|numeric|min:0',
            'materiales.*.unidad' => 'nullable|string|max:50',
            'materiales.*.costo_unitario' => 'nullable|numeric|min:0',
            'materiales.*.costo_total' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
            'notas' => 'nullable|string',
            'tipo' => 'nullable|string|in:bom,recipe,kit,formula,custom|max:50',
        ]);
        $bom->update($validated);

        return redirect()->route('boms.index');
    }

    public function destroy(Bom $bom): RedirectResponse
    {
        $bom->delete();

        return redirect()->route('boms.index');
    }
}
