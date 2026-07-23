<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\PublicProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ServiceController extends Controller
{
    public function index()
    {
        $ownerId = auth()->user()->getOwnerId();
        $publicProfile = PublicProfile::where('user_id', $ownerId)->first();

        // Auto-sync missing public_profile_id for current owner's services
        if ($publicProfile) {
            Producto::where('owner_id', $ownerId)
                ->where('is_service', true)
                ->whereNull('public_profile_id')
                ->update(['public_profile_id' => $publicProfile->id]);
        }

        $services = Producto::where('owner_id', $ownerId)
            ->where('is_service', true)
            ->with('categoria')
            ->with('providers')
            ->withCount(['appointments as total_reservas'])
            ->withCount(['appointments as reservas_pagadas' => fn ($q) => $q->where('payment_status', 'pagado')])
            ->latest()
            ->get();

        $totalReservas = $services->sum('total_reservas');
        $totalIngresosServicios = $services->sum(fn ($s) => $s->reservas_pagadas * $s->precio_venta);

        $popularServices = $services->sortByDesc('total_reservas')->take(5)->values()->map(fn ($s) => [
            'name' => $s->nombre,
            'reservas' => (int) $s->total_reservas,
            'ingresos' => (float) $s->reservas_pagadas * (float) $s->precio_venta,
        ]);

        $serviceCategories = Categoria::whereHas('productos', fn ($q) => $q->where('is_service', true))
            ->withCount(['productos as total' => fn ($q) => $q->where('is_service', true)])
            ->get()
            ->map(fn ($c) => ['name' => $c->nombre, 'value' => (int) $c->total]);

        $stats = [
            'total' => $services->count(),
            'activos' => $services->where('activo', true)->count(),
            'totalReservas' => $totalReservas,
            'totalIngresos' => $totalIngresosServicios,
            'promedioReservas' => $services->count() > 0 ? round($totalReservas / $services->count(), 1) : 0,
        ];

        return Inertia::render('appointments/Services', [
            'services' => $services,
            'categorias' => Categoria::where('owner_id', $ownerId)
                ->where('tipo', 'servicio')
                ->where('mostrar_en_perfil', true)
                ->get(),
            'employees' => User::where(function ($q) use ($ownerId) {
                $q->where('id', $ownerId)
                    ->orWhere('creator_id', $ownerId);
            })
                ->where(function ($q) {
                    $q->whereHas('empleado', fn ($q2) => $q2->where('estado', 'activo'))
                        ->orWhereHas('roles', fn ($q2) => $q2->whereIn('name', ['Administrador', 'Super Admin', 'Master']));
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'stats' => $stats,
            'popularServices' => $popularServices,
            'serviceCategories' => $serviceCategories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'duracion' => 'required|integer|min:1',
            'precio_venta' => 'required|numeric|min:0',
            'categoria_id' => 'required|exists:categorias,id',
            'activo' => 'boolean',
            'requires_appointment' => 'boolean',
            'provider_ids' => 'nullable|array',
            'provider_ids.*' => 'exists:users,id',
            'imagen' => 'required|image|max:2048',
            'imagen2' => 'nullable|image|max:2048',
            'imagen3' => 'nullable|image|max:2048',
            'imagen4' => 'nullable|image|max:2048',
            'imagen5' => 'nullable|image|max:2048',
        ]);

        $validated['is_service'] = true;
        $validated['codigo'] = 'SRV-'.strtoupper(Str::random(6));
        $validated['precio_compra'] = 0;
        $validated['stock_minimo'] = 0;
        $validated['peso_base'] = 0;
        $validated['mostrar_en_perfil'] = true;
        $validated['owner_id'] = auth()->user()->getOwnerId();
        $validated['user_id'] = auth()->id();

        $categoria = Categoria::find($validated['categoria_id']);
        if ($categoria && $categoria->public_profile_id) {
            $validated['public_profile_id'] = $categoria->public_profile_id;
        } else {
            $publicProfile = PublicProfile::where('owner_id', auth()->user()->getOwnerId())->first();
            if ($publicProfile) {
                $validated['public_profile_id'] = $publicProfile->id;
            }
        }

        for ($i = 1; $i <= 5; $i++) {
            $key = 'imagen'.($i === 1 ? '' : $i);
            if ($request->hasFile($key)) {
                $validated[$key] = $request->file($key)->store('productos', 'public');
            }
        }

        $service = Producto::create($validated);

        if ($request->filled('provider_ids')) {
            $service->providers()->sync($request->input('provider_ids'));
        }

        return back()->with('success', 'Servicio creado correctamente.');
    }

    public function update(Request $request, Producto $service)
    {
        $validated = collect($request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'duracion' => 'required|integer|min:1',
            'precio_venta' => 'required|numeric|min:0',
            'categoria_id' => 'required|exists:categorias,id',
            'activo' => 'boolean',
            'requires_appointment' => 'boolean',
            'provider_ids' => 'nullable|array',
            'provider_ids.*' => 'exists:users,id',
            'imagen' => 'nullable|image|max:2048',
            'imagen2' => 'nullable|image|max:2048',
            'imagen3' => 'nullable|image|max:2048',
            'imagen4' => 'nullable|image|max:2048',
            'imagen5' => 'nullable|image|max:2048',
        ]))->except(['imagen', 'imagen2', 'imagen3', 'imagen4', 'imagen5'])->toArray();

        $categoria = Categoria::find($validated['categoria_id']);
        if ($categoria && $categoria->public_profile_id) {
            $validated['public_profile_id'] = $categoria->public_profile_id;
        }

        for ($i = 1; $i <= 5; $i++) {
            $key = 'imagen'.($i === 1 ? '' : $i);
            if ($request->hasFile($key)) {
                if ($service->{$key}) {
                    Storage::disk('public')->delete($service->{$key});
                }
                $validated[$key] = $request->file($key)->store('productos', 'public');
            }
        }

        $service->update($validated);

        if ($request->has('provider_ids')) {
            $service->providers()->sync($request->input('provider_ids', []));
        }

        return back()->with('success', 'Servicio actualizado.');
    }

    public function destroy(Producto $service)
    {
        $service->providers()->detach();
        $service->delete();

        return back()->with('success', 'Servicio eliminado.');
    }
}
