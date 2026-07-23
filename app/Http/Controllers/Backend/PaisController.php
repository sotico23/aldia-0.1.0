<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:2|unique:countries,code',
            'name' => 'required|string|max:100',
            'currency_code' => 'required|string|max:3',
            'currency_symbol' => 'required|string|max:10',
            'currency_decimals' => 'required|integer|min:0|max:4',
            'locale' => 'required|string|max:10',
            'timezone' => 'required|string|max:50',
            'phone_code' => 'required|string|max:5',
            'tax_name' => 'required|string|max:20',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'fiscal_id_label' => 'required|string|max:20',
            'fiscal_id_pattern' => 'nullable|string|max:50',
            'date_format' => 'required|string|max:20',
            'is_active' => 'required|boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);

        Country::create($validated);

        return back();
    }

    public function update(Request $request, Country $country): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'currency_code' => 'required|string|max:3',
            'currency_symbol' => 'required|string|max:10',
            'currency_decimals' => 'required|integer|min:0|max:4',
            'locale' => 'required|string|max:10',
            'timezone' => 'required|string|max:50',
            'phone_code' => 'required|string|max:5',
            'tax_name' => 'required|string|max:20',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'fiscal_id_label' => 'required|string|max:20',
            'fiscal_id_pattern' => 'nullable|string|max:50',
            'date_format' => 'required|string|max:20',
            'is_active' => 'required|boolean',
        ]);

        $country->update($validated);

        return back();
    }

    public function destroy(Country $country): RedirectResponse
    {
        $country->delete();

        return back();
    }

    public function toggle(Country $country): RedirectResponse
    {
        $country->update(['is_active' => ! $country->is_active]);

        return back();
    }
}
