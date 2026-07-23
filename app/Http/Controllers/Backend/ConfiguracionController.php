<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ConfiguracionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.configuracion.create', only: ['create', 'store']),
            new Middleware('permission:admin.configuracion.edit', only: ['edit', 'update']),
            new Middleware('permission:admin.configuracion.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $configuraciones = Configuracion::where('owner_id', Auth::user()->getOwnerId())
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Backend/Configuracion/Index', ['configuraciones' => $configuraciones]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:100',
            'valor' => 'nullable|string',
            'tipo' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string',
            'categoria' => 'nullable|string|max:100',
            'editable' => 'nullable|boolean',
        ]);

        $validated['owner_id'] = Auth::user()->getOwnerId();
        Configuracion::create($validated);

        return redirect()->route('configuracion.index');
    }

    public function update(Request $request, Configuracion $configuracion): RedirectResponse
    {
        if ($configuracion->owner_id !== Auth::user()->getOwnerId()) {
            abort(403);
        }

        $validated = $request->validate([
            'clave' => 'required|string|max:100',
            'valor' => 'nullable|string',
            'tipo' => 'nullable|string|max:50',
            'descripcion' => 'nullable|string',
            'categoria' => 'nullable|string|max:100',
            'editable' => 'nullable|boolean',
        ]);
        $configuracion->update($validated);

        return redirect()->route('configuracion.index');
    }

    public function destroy(Configuracion $configuracion): RedirectResponse
    {
        if ($configuracion->owner_id !== Auth::user()->getOwnerId()) {
            abort(403);
        }

        $configuracion->delete();

        return redirect()->route('configuracion.index');
    }
}
