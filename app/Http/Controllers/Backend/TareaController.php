<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\Producto;
use App\Models\Tarea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TareaController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $ownerId = $user->getOwnerId();

        // Si es admin/empleador, ve todas las tareas de sus empleados
        // Si es empleado, solo ve sus tareas
        if ($user->hasRole('Empleado')) {
            $tareas = Tarea::with(['usuario:id,name', 'empleado:id,name'])
                ->where('empleado_id', '=', $user->id)
                ->orderBy('fecha_limite', 'asc')
                ->orderBy('prioridad', 'desc')
                ->get();
        } else {
            // Admin ve todas las tareas de su empresa
            $tareas = Tarea::with(['usuario:id,name', 'empleado:id,name'])
                ->where('user_id', '=', $ownerId)
                ->orderBy('fecha_limite', 'asc')
                ->orderBy('prioridad', 'desc')
                ->get();
        }

        // Lista de empleados para asignar tareas (solo para admins)
        $empleados = [];
        if (! $user->hasRole('Empleado')) {
            $empleados = Empleado::with('user:id,name')
                ->where('creator_id', '=', $ownerId)
                ->where('estado', '=', 'activo')
                ->get()
                ->map(function ($emp) {
                    return [
                        'id' => $emp->user_id,
                        'nombre' => $emp->nombre.' '.$emp->apellido,
                    ];
                });
        }

        // Lista de productos para asignar a tareas
        $productos = Producto::where('user_id', $ownerId)
            ->where('activo', true)
            ->select('id', 'nombre', 'unidad_medida', 'cantidad_medida', 'tipo_medida', 'medida_pesable')
            ->get();

        return Inertia::render('Backend/Tareas/Index', [
            'tareas' => $tareas,
            'empleados' => $empleados,
            'productos' => $productos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();

        // Solo admins pueden crear tareas
        if ($user->hasRole('Empleado')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permiso para crear tareas');
        }

        $ownerId = $user->getOwnerId();

        $validated = $request->validate([
            'empleado_id' => 'required|exists:users,id',
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'prioridad' => 'required|in:baja,media,alta,urgente',
            'fecha_limite' => 'nullable|date',
            'productos' => 'nullable|array',
        ]);

        Tarea::create([
            'user_id' => $ownerId,
            'empleado_id' => $validated['empleado_id'],
            'titulo' => $validated['titulo'],
            'descripcion' => $validated['descripcion'] ?? null,
            'prioridad' => $validated['prioridad'],
            'fecha_limite' => $validated['fecha_limite'] ?? null,
            'productos_json' => $validated['productos'] ?? null,
            'estado' => 'pendiente',
        ]);

        return redirect()->route('tareas.index')->with('success', 'Tarea asignada correctamente');
    }

    public function update(Request $request, Tarea $tarea): RedirectResponse
    {
        $user = Auth::user();

        // Empleados solo pueden cambiar el estado de sus propias tareas
        if ($user->hasRole('Empleado') && $tarea->empleado_id !== $user->id) {
            return redirect()->route('dashboard')->with('error', 'No puedes modificar esta tarea');
        }

        $validated = $request->validate([
            'estado' => 'required|in:pendiente,en_progreso,completada,cancelada',
        ]);

        $tarea->update($validated);

        return redirect()->back()->with('success', 'Tarea actualizada');
    }

    public function destroy(Tarea $tarea): RedirectResponse
    {
        $user = Auth::user();

        // Solo admins pueden eliminar tareas
        if ($user->hasRole('Empleado')) {
            return redirect()->route('dashboard')->with('error', 'No tienes permiso para eliminar tareas');
        }

        $tarea->delete();

        return redirect()->route('tareas.index')->with('success', 'Tarea eliminada');
    }
}
