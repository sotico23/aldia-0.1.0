<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCountryRequest;
use App\Http\Requests\UpdateCountryRequest;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class PaisController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:admin.countries.create', only: ['store']),
            new Middleware('permission:admin.countries.edit', only: ['update', 'toggle']),
            new Middleware('permission:admin.countries.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $countries = Country::orderBy('name')->get();

        return Inertia::render('Backend/Paises/Index', ['countries' => $countries]);
    }

    public function store(StoreCountryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['code'] = strtoupper($validated['code']);

        Country::create($validated);

        return back();
    }

    public function update(UpdateCountryRequest $request, Country $country): RedirectResponse
    {
        $validated = $request->validated();

        $country->update($validated);

        return back();
    }

    public function destroy(Country $country): RedirectResponse
    {
        if ($country->users()->exists()) {
            return back()->withErrors(['country' => 'No se puede eliminar un país asociado a usuarios. Puedes desactivarlo.']);
        }

        $country->delete();

        return back();
    }

    public function toggle(Country $country): RedirectResponse
    {
        $country->update(['is_active' => ! $country->is_active]);

        return back();
    }
}
