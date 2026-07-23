<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UserProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        $profileData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rut' => $user->rut,
            'telefono' => $user->telefono,
            'direccion' => $user->direccion,
            'giro' => $user->giro,
            'ciudad' => $user->ciudad,
            'region' => $user->region,
            'comuna' => $user->comuna,
            'fecha_nacimiento' => $user->fecha_nacimiento,
            'genero' => $user->genero,
            'tipo_entidad' => $user->tipo_entidad,
            'job' => $user->job,
            'location' => $user->location,
            'profile_photo_url' => $user->profile_photo_url,
            'cover_photo_path' => $user->cover_photo_path,
            'business_logo_url' => $user->businessLogoUrl(),
            'business_name' => $user->business_name,
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'two_factor_enabled' => ! empty($user->two_factor_secret),
        ];

        return Inertia::render('settings/MiInformacion', [
            'profile' => $profileData,
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'rut' => 'nullable|string|max:20',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:500',
            'giro' => 'nullable|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'comuna' => 'nullable|string|max:255',
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|string|max:50',
            'job' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'business_name' => 'nullable|string|max:255',
        ]);

        $user->update($validated);

        if ($request->hasFile('business_logo')) {
            if ($user->business_logo_path) {
                Storage::disk('public')->delete($user->business_logo_path);
            }
            $path = $request->file('business_logo')->store('logos', 'public');
            $user->update(['business_logo_path' => $path]);
        }

        if ($request->boolean('remove_business_logo') && $user->business_logo_path) {
            Storage::disk('public')->delete($user->business_logo_path);
            $user->update(['business_logo_path' => null]);
        }

        return redirect()->back()->with('success', 'Información actualizada correctamente.');
    }
}
