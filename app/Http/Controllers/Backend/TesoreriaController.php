<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Tesoreria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TesoreriaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:finanzas.tesoreria.create', only: ['create', 'store']),
            new Middleware('permission:finanzas.tesoreria.edit', only: ['edit', 'update']),
            new Middleware('permission:finanzas.tesoreria.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $ownerId = Auth::user()->getOwnerId();
        $tesorerias = Tesoreria::where('owner_id', $ownerId)->orderBy('fecha', 'desc')->paginate(15);

        $ingresos = Tesoreria::where('owner_id', $ownerId)->where('tipo', 'ingreso')->sum('monto');
        $egresos = Tesoreria::where('owner_id', $ownerId)->where('tipo', 'egreso')->sum('monto');
        $saldo = $ingresos - $egresos;

        return Inertia::render('Backend/Tesoreria/Index', [
            'tesorerias' => $tesorerias,
            'totales' => [
                'ingresos' => $ingresos,
                'egresos' => $egresos,
                'saldo' => $saldo,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tipo' => 'required|string|max:50',
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string',
            'cuenta_bancaria_id' => 'nullable|integer',
            'fecha' => 'nullable|date',
            'referencia' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:100',
            'estado' => 'required|string|max:50',
        ]);

        $validated['owner_id'] = Auth::user()->getOwnerId();

        Tesoreria::create($validated);

        return redirect()->route('tesoreria.index');
    }

    public function update(Request $request, Tesoreria $tesoreria): RedirectResponse
    {
        $validated = $request->validate([
            'tipo' => 'required|string|max:50',
            'monto' => 'required|numeric|min:0',
            'descripcion' => 'nullable|string',
            'cuenta_bancaria_id' => 'nullable|integer',
            'fecha' => 'nullable|date',
            'referencia' => 'nullable|string|max:255',
            'categoria' => 'nullable|string|max:100',
            'estado' => 'required|string|max:50',
        ]);
        $tesoreria->update($validated);

        return redirect()->route('tesoreria.index');
    }

    public function destroy(Tesoreria $tesoreria): RedirectResponse
    {
        $tesoreria->delete();

        return redirect()->route('tesoreria.index');
    }
}
