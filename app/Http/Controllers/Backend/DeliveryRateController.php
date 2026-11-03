<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DeliveryRateController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:flota.entregas.create', only: ['store']),
            new Middleware('permission:flota.entregas.edit', only: ['update']),
            new Middleware('permission:flota.entregas.delete', only: ['destroy']),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['owner_id'] = Auth::user()->getOwnerId();

        DeliveryRate::create($data);

        return redirect()->route('entregas.index')->with('success', 'Tarifa de despacho creada.');
    }

    public function update(Request $request, DeliveryRate $deliveryRate): RedirectResponse
    {
        $deliveryRate->update($this->validatedData($request));

        return redirect()->route('entregas.index')->with('success', 'Tarifa de despacho actualizada.');
    }

    public function destroy(DeliveryRate $deliveryRate): RedirectResponse
    {
        $deliveryRate->delete();

        return redirect()->route('entregas.index')->with('success', 'Tarifa de despacho eliminada.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'calculation_type' => ['required', 'in:comuna,rango_km,valor_fijo_km'],
            'commune' => ['nullable', 'required_if:calculation_type,comuna', 'string', 'max:120'],
            'km_from' => ['nullable', 'required_if:calculation_type,rango_km', 'numeric', 'min:0', 'max:99999'],
            'km_to' => ['nullable', 'required_if:calculation_type,rango_km', 'numeric', 'gt:km_from', 'max:99999'],
            'cost' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($data['calculation_type'] !== 'comuna') {
            $data['commune'] = null;
        }

        if ($data['calculation_type'] !== 'rango_km') {
            $data['km_from'] = null;
            $data['km_to'] = null;
        }

        if ($data['calculation_type'] === 'rango_km' && (float) $data['km_from'] >= (float) $data['km_to']) {
            throw ValidationException::withMessages([
                'km_to' => 'El límite superior debe ser mayor que el límite inferior.',
            ]);
        }

        return $data;
    }
}
