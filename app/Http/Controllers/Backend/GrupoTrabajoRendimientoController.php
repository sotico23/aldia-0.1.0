<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\GrupoTrabajo;
use App\Models\GrupoTrabajoAsignacion;
use App\Services\GrupoTrabajoRendimientoService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class GrupoTrabajoRendimientoController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly GrupoTrabajoRendimientoService $rendimientoService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:flota.grupos-trabajo.edit', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $ownerId = $user->getOwnerId();

        $grupos = GrupoTrabajo::with(['conductores', 'asignaciones'])
            ->where('owner_id', $ownerId)
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get();

        $now = Carbon::now();
        $fechaInicio = $request->input('fecha_inicio', $now->copy()->startOfMonth()->toDateString());
        $fechaFin = $request->input('fecha_fin', $now->copy()->endOfMonth()->toDateString());
        $grupoIds = $request->input('grupo_ids');

        if ($grupoIds !== null && ! is_array($grupoIds)) {
            $grupoIds = [$grupoIds];
        }

        $asignacionesActivas = $this->rendimientoService->asignacionesActivas($ownerId);

        $proximoCorte = $asignacionesActivas->min('fecha_fin')
            ?? $now->copy()->endOfMonth()->toDateString();

        $diasParaCorte = $now->diffInDays(Carbon::parse($proximoCorte), false);

        $rendimiento = $this->rendimientoService->calcularPorGrupos($ownerId, $fechaInicio, $fechaFin, $grupoIds);
        $tendencia = $this->rendimientoService->calcularTendenciaMensual($ownerId, 6);
        $comparativa = $this->rendimientoService->calcularComparativaGrupos($ownerId, $fechaInicio, $fechaFin, $grupoIds);

        return Inertia::render('Backend/GruposTrabajo/Rendimiento', [
            'grupos' => $grupos,
            'asignacionesActivas' => $asignacionesActivas,
            'rendimiento' => $rendimiento,
            'tendencia' => $tendencia,
            'comparativa' => $comparativa,
            'proximoCorte' => $proximoCorte,
            'diasParaCorte' => $diasParaCorte,
            'fechaInicio' => $fechaInicio,
            'fechaFin' => $fechaFin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasPermissionTo('flota.grupos-trabajo.edit'), 403);

        $validated = $request->validate([
            'grupo_trabajo_id' => 'required|exists:grupo_trabajos,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'meta_monto' => 'nullable|numeric|min:0',
            'meta_cantidad' => 'nullable|integer|min:0',
            'meta_kg' => 'nullable|numeric|min:0',
            'meta_l' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string|max:1000',
        ]);

        $validated['owner_id'] = Auth::user()->getOwnerId();
        $validated['user_id'] = GrupoTrabajo::find($validated['grupo_trabajo_id'])->user_id ?? Auth::id();
        $validated['meta_monto'] = $validated['meta_monto'] ?? 0;
        $validated['meta_cantidad'] = $validated['meta_cantidad'] ?? 0;
        $validated['meta_kg'] = $validated['meta_kg'] ?? 0;
        $validated['meta_l'] = $validated['meta_l'] ?? 0;

        GrupoTrabajoAsignacion::create($validated);

        return redirect()->route('grupos-trabajo.rendimiento.index')
            ->with('success', 'Asignación creada exitosamente.');
    }

    public function update(Request $request, GrupoTrabajoAsignacion $asignacion): RedirectResponse
    {
        abort_unless(Auth::user()->hasPermissionTo('flota.grupos-trabajo.edit'), 403);

        $validated = $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'meta_monto' => 'nullable|numeric|min:0',
            'meta_cantidad' => 'nullable|integer|min:0',
            'meta_kg' => 'nullable|numeric|min:0',
            'meta_l' => 'nullable|numeric|min:0',
            'estado' => 'nullable|in:activa,completada,cancelada',
            'notas' => 'nullable|string|max:1000',
        ]);

        $validated['meta_monto'] = $validated['meta_monto'] ?? 0;
        $validated['meta_cantidad'] = $validated['meta_cantidad'] ?? 0;
        $validated['meta_kg'] = $validated['meta_kg'] ?? 0;
        $validated['meta_l'] = $validated['meta_l'] ?? 0;

        $asignacion->update($validated);

        return redirect()->route('grupos-trabajo.rendimiento.index')
            ->with('success', 'Asignación actualizada exitosamente.');
    }

    public function destroy(GrupoTrabajoAsignacion $asignacion): RedirectResponse
    {
        abort_unless(Auth::user()->hasPermissionTo('flota.grupos-trabajo.delete'), 403);

        $asignacion->delete();

        return redirect()->route('grupos-trabajo.rendimiento.index')
            ->with('success', 'Asignación eliminada.');
    }
}
