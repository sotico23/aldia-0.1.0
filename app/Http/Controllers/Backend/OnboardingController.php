<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PublicProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Si ya completó el onboarding, redirigir al dashboard
        if ($user->tipo_entidad) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('onboarding/index', [
            'user' => $user,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'tipo_entidad' => 'required|string|in:independiente,negocio,empresa',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:100',
            // Datos del perfil público
            'store_name' => 'required|string|max:255',
            'store_description' => 'nullable|string|max:1000',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? $user->email,
            'telefono' => $validated['telefono'],
            'direccion' => $validated['direccion'],
            'ciudad' => $validated['ciudad'],
            'tipo_entidad' => $validated['tipo_entidad'],
        ]);

        // Crear perfil público inicial
        PublicProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'title' => $validated['store_name'],
                'slug' => Str::slug($validated['store_name']).'-'.$user->id,
                'description' => $validated['store_description'],
                'phone' => $validated['telefono'],
                'email' => $validated['email'] ?? $user->email,
                'is_active' => true,
            ]
        );

        return redirect()->route('dashboard')->with('success', '¡Perfil completado correctamente! Bienvenido a bordo.');
    }
}
