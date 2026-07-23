<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Publicacion;
use Inertia\Inertia;
use Inertia\Response;

class ComunidadController extends Controller
{
    public function index(): Response
    {
        $publicaciones = Publicacion::withoutGlobalScopes()
            ->with(['user', 'comentarios' => function ($q) {
                $q->withoutGlobalScopes()->whereNull('parent_id')->with(['user', 'replies' => function ($sq) {
                    $sq->withoutGlobalScopes()->with('user');
                }])->latest();
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('Backend/Comunidad/Index', [
            'publicaciones' => $publicaciones,
        ]);
    }
}
