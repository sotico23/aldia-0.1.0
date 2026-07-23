<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\PublicProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicProfileController extends Controller
{
    public function edit(Request $request)
    {
        return Inertia::render('settings/public-profile', [
            'publicProfile' => $request->user()->publicProfile ?? [
                'title' => '',
                'slug' => '',
                'description' => '',
                'phone' => '',
                'email' => '',
                'is_active' => false,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'required|string|alpha_dash|max:255|unique:public_profiles,slug,'.optional($request->user()->publicProfile)->id,
            'description' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
        ]);

        $request->user()->publicProfile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        return back()->with('success', 'Perfil público actualizado correctamente.');
    }

    public function toggleActive(Request $request)
    {
        $profile = $request->user()->publicProfile;

        if (! $profile) {
            $profile = $request->user()->publicProfile()->create([
                'title' => $request->user()->name."'s Store",
                'slug' => str($request->user()->name)->slug()->toString(),
                'is_active' => true,
            ]);
        } else {
            $profile->update(['is_active' => ! $profile->is_active]);
        }

        return back()->with('success', $profile->is_active ? 'Tu tienda ahora es visible en el marketplace.' : 'Tu tienda ha sido ocultada del marketplace.');
    }

    /**
     * Toggle is_official or is_verified status (Admin only)
     */
    public function toggleStatus(Request $request, PublicProfile $publicProfile): RedirectResponse
    {
        $validated = $request->validate([
            'field' => 'required|in:is_official,is_verified',
            'value' => 'required|boolean',
        ]);

        $publicProfile->update([
            $validated['field'] => $validated['value'],
        ]);

        $fieldLabel = $validated['field'] === 'is_official' ? 'Oficial' : 'Verificada';
        $status = $validated['value'] ? 'activada' : 'desactivada';

        return back()->with('success', "Estado {$fieldLabel} {$status} correctamente.");
    }
}
