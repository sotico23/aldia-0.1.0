<?php

namespace App\Http\Controllers\Backend;

use App\Exports\VehiculosExport;
use App\Helpers\SearchHelper;
use App\Http\Controllers\Controller;
use App\Imports\VehiculosImport;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class VehiculoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:flota.vehiculos.create', only: ['create', 'store']),
            new Middleware('permission:flota.vehiculos.edit', only: ['edit', 'update']),
            new Middleware('permission:flota.vehiculos.delete', only: ['destroy']),
            new Middleware('permission:flota.vehiculos.export', only: ['exportCsv', 'exportExcel']),
            new Middleware('permission:flota.vehiculos.import', only: ['importCsv', 'importExcel']),
        ];
    }

    public function index(Request $request): Response
    {
        $ownerId = Auth::user()->getOwnerId();
        $query = Vehiculo::where('owner_id', $ownerId);

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('placa', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%")
                    ->orWhere('imei', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        $vehiculos = $query
            ->select('id', 'placa', 'imei', 'marca', 'modelo', 'tipo', 'año', 'color', 'lat', 'lng', 'velocidad', 'ultima_actualizacion', 'estado', 'kilometraje', 'notas', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $ids = $vehiculos->pluck('id')->toArray();
        $fotosData = [];

        if (! empty($ids)) {
            $fotosData = Vehiculo::whereIn('id', $ids)->pluck('fotos', 'id')->toArray();
        }

        $vehiculos->getCollection()->transform(function ($v) use ($fotosData) {
            $fotosJson = $fotosData[$v->id] ?? null;
            $v->fotos = $fotosJson ? json_decode($fotosJson, true) : [];

            return $v;
        });

        return Inertia::render('Backend/Vehiculos/Index', [
            'vehiculos' => $vehiculos,
            'filters' => $request->only(['search', 'tipo', 'estado']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        $request->merge(['placa' => strtoupper($request->placa)]);

        $validated = $request->validate([
            'placa' => [
                'required',
                'string',
                'max:20',
                'unique:vehiculos,placa,NULL,id,owner_id,'.$ownerId,
                'regex:/^([A-Z]{2}-?[0-9]{4}|[A-Z]{4}-?[0-9]{2})$/i',
            ],
            'imei' => 'nullable|string|unique:vehiculos,imei,NULL,id,owner_id,'.$ownerId,
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'tipo' => 'nullable|string|max:50',
            'año' => 'nullable|integer',
            'color' => 'nullable|string|max:50',
            'estado' => 'required|string|in:disponible,en_ruta,mantenimiento,fuera_servicio,requiere_atencion',
            'kilometraje' => 'nullable|numeric|min:0',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'velocidad' => 'nullable|integer',
            'ultima_actualizacion' => 'nullable|date',
            'notas' => 'nullable|string',
            'fotos' => 'nullable|array|max:3',
            'fotos.*' => 'nullable|string',
        ], [
            'placa.regex' => 'El formato de la placa debe ser chileno (ej: AB1234, AB-1234, ABCD12, ABCD-12)',
        ]);

        $validated['owner_id'] = $ownerId;

        // Guardar fotos como JSON
        if (! empty($validated['fotos'])) {
            $validated['fotos'] = json_encode($validated['fotos']);
        }

        Vehiculo::create($validated);

        return redirect()->route('vehiculos.index');
    }

    public function update(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        if ($vehiculo->owner_id !== $ownerId) {
            abort(403);
        }

        $request->merge(['placa' => strtoupper($request->placa)]);

        $validated = $request->validate([
            'placa' => [
                'nullable',
                'string',
                'max:20',
                'unique:vehiculos,placa,'.$vehiculo->id.',id,owner_id,'.$ownerId,
                'regex:/^([A-Z]{2}-?[0-9]{4}|[A-Z]{4}-?[0-9]{2})$/i',
            ],
            'imei' => 'nullable|string|unique:vehiculos,imei,'.$vehiculo->id.',id,owner_id,'.$ownerId,
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'tipo' => 'nullable|string|max:50',
            'año' => 'nullable|integer',
            'color' => 'nullable|string|max:50',
            'estado' => 'nullable|string|in:disponible,en_ruta,mantenimiento,fuera_servicio,requiere_atencion',
            'kilometraje' => 'nullable|numeric|min:0',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'velocidad' => 'nullable|integer',
            'ultima_actualizacion' => 'nullable|date',
            'notas' => 'nullable|string',
            'fotos' => 'nullable|array|max:3',
            'fotos.*' => 'nullable|string',
        ], [
            'placa.regex' => 'El formato de la placa debe ser chileno (ej: AB1234, AB-1234, ABCD12, ABCD-12)',
        ]);

        // Procesar fotos
        if (isset($validated['fotos'])) {
            $validated['fotos'] = json_encode($validated['fotos']);
        }

        $vehiculo->update($validated);

        return redirect()->route('vehiculos.index');
    }

    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        if ($vehiculo->owner_id !== $ownerId) {
            abort(403);
        }

        $vehiculo->delete();

        return redirect()->route('vehiculos.index');
    }

    public function exportCsv(Request $request)
    {
        $ownerId = Auth::user()->getOwnerId();
        $query = Vehiculo::where('owner_id', $ownerId)->orderBy('id');

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('placa', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%");
            });
        }

        $filename = 'vehiculos_'.now()->format('Ymd_His').'.csv';

        return Excel::download(new VehiculosExport($query), $filename, \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportExcel(Request $request)
    {
        $ownerId = Auth::user()->getOwnerId();
        $query = Vehiculo::where('owner_id', $ownerId)->orderBy('id');

        if ($request->filled('search')) {
            $search = SearchHelper::escapeLike($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('placa', 'like', "%{$search}%")
                    ->orWhere('marca', 'like', "%{$search}%")
                    ->orWhere('modelo', 'like', "%{$search}%");
            });
        }

        return Excel::download(new VehiculosExport($query), 'vehiculos_'.now()->format('Ymd_His').'.xlsx');
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt',
        ]);

        Excel::import(new VehiculosImport, $request->file('archivo'));

        return redirect()->route('vehiculos.index')->with('success', 'Vehículos importados correctamente.');
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls',
        ]);

        Excel::import(new VehiculosImport, $request->file('archivo'));

        return redirect()->route('vehiculos.index')->with('success', 'Vehículos importados correctamente.');
    }

    public function actualizarTracking(Request $request, Vehiculo $vehiculo): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        if ($vehiculo->owner_id !== $ownerId) {
            abort(403);
        }

        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'velocidad' => 'nullable|integer|min:0',
        ]);

        $vehiculo->update([
            'lat' => (float) $validated['lat'],
            'lng' => (float) $validated['lng'],
            'velocidad' => $validated['velocidad'] ?? 0,
            'ultima_actualizacion' => now(),
            'estado' => ($validated['velocidad'] ?? 0) > 0 ? 'en_ruta' : 'disponible',
        ]);

        return redirect()->back()->with('success', 'Ubicación actualizada correctamente');
    }

    public function simularTracking(Vehiculo $vehiculo): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        if ($vehiculo->owner_id !== $ownerId) {
            abort(403);
        }

        $chileCenter = [
            'lat' => -33.4489 + (rand(-100, 100) / 1000),
            'lng' => -70.6693 + (rand(-100, 100) / 1000),
        ];

        $velocidad = rand(0, 120);

        $vehiculo->update([
            'lat' => $chileCenter['lat'],
            'lng' => $chileCenter['lng'],
            'velocidad' => $velocidad,
            'ultima_actualizacion' => now(),
            'estado' => $velocidad > 0 ? 'en_ruta' : 'disponible',
        ]);

        return redirect()->back()->with('success', 'Datos de tracking simulados correctamente');
    }

    public function limpiarTracking(Vehiculo $vehiculo): RedirectResponse
    {
        $ownerId = Auth::user()->getOwnerId();
        if ($vehiculo->owner_id !== $ownerId) {
            abort(403);
        }

        $vehiculo->update([
            'lat' => null,
            'lng' => null,
            'velocidad' => 0,
            'ultima_actualizacion' => null,
            'estado' => 'disponible',
        ]);

        return redirect()->back()->with('success', 'Datos de tracking limpiados');
    }
}
