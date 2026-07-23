<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Tarea;
use Inertia\Inertia;
use Inertia\Response;

class ListaPendientesController extends Controller
{
    public function index(): Response
    {
        $pendientes = Tarea::with(['usuario', 'empleado'])
            ->where('user_id', auth()->id())
            ->orWhere('empleado_id', auth()->id())
            ->orderBy('fecha_limite', 'asc')
            ->orderBy('prioridad', 'desc')
            ->get();

        return Inertia::render('Backend/ListaPendientes/Index', ['pendientes' => $pendientes]);
    }
}
