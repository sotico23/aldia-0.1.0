<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Conductor;
use App\Models\Empleado;
use App\Models\GrupoTrabajo;
use App\Models\User;
use App\Services\GrupoTrabajoRendimientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GrupoTrabajoController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly GrupoTrabajoRendimientoService $rendimientoService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:flota.grupos-trabajo.create', only: ['create', 'store']),
            new Middleware('permission:flota.grupos-trabajo.edit', only: ['edit', 'update']),
            new Middleware('permission:flota.grupos-trabajo.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $user = Auth::user();
        $ownerId = $user->getOwnerId();
        $canManage = $user->hasPermissionTo('flota.grupos-trabajo.viewAny') || $user->highestRoleLevel() === 0;

        if ($canManage) {
            $grupos = GrupoTrabajo::with(['miembros', 'conductores'])
                ->where('owner_id', $ownerId)
                ->orderBy('created_at', 'desc')
                ->get();

            $grupos->each(function ($grupo) use ($ownerId) {
                $metrics = $this->rendimientoService->calcularMetricasGrupo($grupo, $ownerId);

                $grupo->cantidad_ventas = $metrics['cantidad'];
                $grupo->total_ventas = $metrics['monto'];
                $grupo->total_kg = $metrics['kg'];
                $grupo->total_l = $metrics['l'];
            });

            $this->ensureUsersForEmpleados($ownerId);

            $empleados = User::where(function ($query) use ($ownerId) {
                $query->where('creator_id', $ownerId)
                    ->orWhere('id', $ownerId)
                    ->orWhereHas('empleado', fn ($q) => $q->where('owner_id', $ownerId));
            })->orderBy('name')
                ->get(['id', 'name', 'email']);

            $conductores = Conductor::where('owner_id', $ownerId)->orderBy('nombre')->get();
        } else {
            $grupos = GrupoTrabajo::with(['miembros', 'conductores'])
                ->where('owner_id', $ownerId)
                ->whereHas('miembros', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $empleados = [];
            $conductores = [];
        }

        return Inertia::render('Backend/GruposTrabajo/Index', [
            'grupos' => $grupos,
            'empleados' => $empleados,
            'conductores' => $conductores,
            'puedeGestionar' => $canManage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->hasPermissionTo('flota.grupos-trabajo.create'), 403);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'estado' => 'nullable|in:activo,inactivo',
            'miembros' => 'nullable|array',
            'miembros.*' => 'exists:users,id',
            'conductores' => 'nullable|array',
            'conductores.*' => 'exists:conductores,id',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['owner_id'] = Auth::user()->getOwnerId();
        $validated['estado'] = $validated['estado'] ?? 'activo';

        try {
            DB::transaction(function () use ($validated) {
                $grupo = GrupoTrabajo::create($validated);

                if (! empty($validated['miembros'])) {
                    $grupo->miembros()->sync($validated['miembros']);
                }

                if (! empty($validated['conductores'])) {
                    $grupo->conductores()->sync($validated['conductores']);
                }
            });

            return redirect()->route('grupos-trabajo.index')->with('success', 'Grupo de trabajo creado exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al crear el grupo de trabajo: '.$e->getMessage());
        }
    }

    public function update(Request $request, GrupoTrabajo $grupoTrabajo): RedirectResponse
    {
        abort_unless(Auth::user()->hasPermissionTo('flota.grupos-trabajo.edit'), 403);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'estado' => 'nullable|in:activo,inactivo',
            'miembros' => 'nullable|array',
            'miembros.*' => 'exists:users,id',
            'conductores' => 'nullable|array',
            'conductores.*' => 'exists:conductores,id',
        ]);

        try {
            DB::transaction(function () use ($grupoTrabajo, $validated) {
                $grupoTrabajo->update($validated);

                if (isset($validated['miembros'])) {
                    $grupoTrabajo->miembros()->sync($validated['miembros']);
                } else {
                    $grupoTrabajo->miembros()->sync([]);
                }

                if (isset($validated['conductores'])) {
                    $grupoTrabajo->conductores()->sync($validated['conductores']);
                } else {
                    $grupoTrabajo->conductores()->sync([]);
                }
            });

            return redirect()->route('grupos-trabajo.index')->with('success', 'Grupo de trabajo actualizado exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al actualizar el grupo de trabajo: '.$e->getMessage());
        }
    }

    public function destroy(GrupoTrabajo $grupoTrabajo): RedirectResponse
    {
        abort_unless(Auth::user()->hasPermissionTo('flota.grupos-trabajo.delete'), 403);

        try {
            DB::transaction(function () use ($grupoTrabajo) {
                $grupoTrabajo->miembros()->detach();
                $grupoTrabajo->conductores()->detach();
                $grupoTrabajo->delete();
            });

            return redirect()->route('grupos-trabajo.index')->with('success', 'Grupo de trabajo eliminado exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al eliminar el grupo de trabajo: '.$e->getMessage());
        }
    }

    public function exportCsv(): StreamedResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        $grupos = GrupoTrabajo::with(['miembros', 'conductores'])
            ->where('owner_id', $ownerId)
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="grupos_trabajo_'.now()->format('Ymd_His').'.csv"',
        ];

        $callback = function () use ($grupos) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Nombre', 'Descripción', 'Color', 'Estado', 'Miembros', 'Conductores', 'Fecha Creación']);

            foreach ($grupos as $grupo) {
                fputcsv($file, [
                    $grupo->id,
                    $grupo->nombre,
                    $grupo->descripcion ?? '',
                    $grupo->color,
                    $grupo->estado,
                    $grupo->miembros->pluck('name')->implode(', '),
                    $grupo->conductores->pluck('nombre')->implode(', '),
                    $grupo->created_at?->format('d/m/Y H:i'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportExcel(): StreamedResponse
    {
        return $this->exportCsv();
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $file = fopen($request->file('file')->getRealPath(), 'r');
            $header = fgetcsv($file);

            $ownerId = Auth::user()->getOwnerId();
            $imported = 0;

            while (($row = fgetcsv($file)) !== false) {
                if (count($row) < 2) {
                    continue;
                }

                GrupoTrabajo::create([
                    'owner_id' => $ownerId,
                    'user_id' => Auth::id(),
                    'nombre' => $row[1] ?? 'Sin nombre',
                    'descripcion' => $row[2] ?? null,
                    'color' => $row[3] ?? '#3B82F6',
                    'estado' => $row[4] ?? 'activo',
                ]);

                $imported++;
            }

            fclose($file);

            return redirect()->back()->with('success', "{$imported} grupos importados correctamente.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al importar: '.$e->getMessage());
        }
    }

    public function importExcel(Request $request): RedirectResponse
    {
        return $this->importCsv($request);
    }

    private function ensureUsersForEmpleados(int $ownerId): void
    {
        $empleadosSinUser = Empleado::whereNull('user_id')
            ->where('owner_id', $ownerId)
            ->get();

        foreach ($empleadosSinUser as $empleado) {
            $user = User::create([
                'creator_id' => Auth::id(),
                'name' => $empleado->nombre.' '.$empleado->apellido,
                'email' => $empleado->email,
                'password' => Hash::make('empleadonuevo'),
                'telefono' => $empleado->telefono,
                'direccion' => $empleado->direccion,
                'email_verified_at' => now(),
            ]);

            $empleado->update(['user_id' => $user->id]);
        }
    }
}
