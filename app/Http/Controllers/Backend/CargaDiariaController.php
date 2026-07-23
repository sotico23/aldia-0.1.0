<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CargaDiaria;
use App\Models\CargaDiariaProducto;
use App\Models\CargaDiariaRenovacion;
use App\Models\CargaDiariaRenovacionDetalle;
use App\Models\Conductor;
use App\Models\Producto;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CargaDiariaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:flota.cargas.create', only: ['create', 'store']),
            new Middleware('permission:flota.cargas.edit', only: ['edit', 'update']),
            new Middleware('permission:flota.cargas.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $cargas = CargaDiaria::with(['vehiculo', 'conductor', 'productos.producto'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $vehiculos = Vehiculo::select('id', 'placa', 'marca', 'modelo')
            ->orderBy('marca')
            ->limit(100)
            ->get();

        $conductores = Conductor::select('id', 'nombre')
            ->orderBy('nombre')
            ->limit(100)
            ->get();

        $productos = Producto::select('id', 'nombre', 'precio_venta')
            ->orderBy('nombre')
            ->limit(100)
            ->get();

        return Inertia::render('Backend/CargaDiaria/Index', [
            'cargas' => $cargas,
            'vehiculos' => $vehiculos,
            'conductores' => $conductores,
            'productos' => $productos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vehiculo_id' => 'required|exists:vehiculos,id',
            'conductor_id' => 'required|exists:conductores,id',
            'fecha' => 'required|date',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
            'productos' => 'nullable|array',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $carga = CargaDiaria::create([
                    'vehiculo_id' => $validated['vehiculo_id'],
                    'conductor_id' => $validated['conductor_id'],
                    'fecha' => $validated['fecha'],
                    'estado' => $validated['estado'] ?? 'pendiente',
                    'notas' => $validated['notas'] ?? null,
                ]);

                if (! empty($validated['productos'])) {
                    foreach ($validated['productos'] as $prod) {
                        CargaDiariaProducto::create([
                            'carga_diaria_id' => $carga->id,
                            'producto_id' => $prod['producto_id'],
                            'cantidad_bordo' => $prod['cantidad'],
                        ]);
                    }
                }
            });

            return redirect()->route('cargas-diarias.index')->with('success', 'Carga diaria registrada con éxito.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al registrar la carga: '.$e->getMessage());
        }
    }

    public function show(CargaDiaria $cargaDiaria): Response
    {
        $cargaDiaria->load(['vehiculo', 'conductor', 'productos.producto', 'renovaciones.detalles.producto']);

        return Inertia::render('Backend/CargaDiaria/Show', [
            'carga' => $cargaDiaria,
        ]);
    }

    public function update(Request $request, CargaDiaria $cargaDiaria): RedirectResponse
    {
        $validated = $request->validate([
            'vehiculo_id' => 'required|exists:vehiculos,id',
            'conductor_id' => 'required|exists:conductores,id',
            'fecha' => 'required|date',
            'estado' => 'required|string|max:50',
            'notas' => 'nullable|string',
            'productos' => 'nullable|array',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad' => 'required|integer|min:0',
        ]);

        try {
            DB::transaction(function () use ($cargaDiaria, $validated) {
                $cargaDiaria->update([
                    'vehiculo_id' => $validated['vehiculo_id'],
                    'conductor_id' => $validated['conductor_id'],
                    'fecha' => $validated['fecha'],
                    'estado' => $validated['estado'],
                    'notas' => $validated['notas'] ?? null,
                ]);

                if (isset($validated['productos'])) {
                    $cargaDiaria->productos()->delete();
                    foreach ($validated['productos'] as $prod) {
                        if ($prod['cantidad'] > 0) {
                            CargaDiariaProducto::create([
                                'carga_diaria_id' => $cargaDiaria->id,
                                'producto_id' => $prod['producto_id'],
                                'cantidad_bordo' => $prod['cantidad'],
                            ]);
                        }
                    }
                }
            });

            return redirect()->route('cargas-diarias.index')->with('success', 'Carga diaria actualizada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al actualizar la carga: '.$e->getMessage());
        }
    }

    public function destroy(CargaDiaria $cargaDiaria): RedirectResponse
    {
        try {
            $cargaDiaria->delete();

            return redirect()->route('cargas-diarias.index')->with('success', 'Carga eliminada correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al eliminar la carga: '.$e->getMessage());
        }
    }

    public function recargar(Request $request, CargaDiaria $cargaDiaria): RedirectResponse
    {
        $validated = $request->validate([
            'notas' => 'nullable|string',
            'ventas_totales' => 'required|numeric|min:0',
            'devoluciones_totales' => 'required|numeric|min:0',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad_bordo' => 'required|integer|min:0',
            'productos.*.cantidad_llena' => 'required|integer|min:0',
            'productos.*.cantidad_vacia' => 'required|integer|min:0',
            'productos.*.cantidad_faltante' => 'required|integer|min:0',
            'productos.*.cantidad_defectuosa' => 'required|integer|min:0',
            'productos.*.cantidad_vendida' => 'required|integer|min:0',
            'productos.*.cantidad_devuelta' => 'required|integer|min:0',
            'crear_nueva_carga' => 'nullable|boolean',
        ]);

        try {
            DB::transaction(function () use ($cargaDiaria, $validated) {
                $ownerId = auth()->user()->getOwnerId();

                $totalLlenos = 0;
                $totalVacios = 0;
                $totalFaltantes = 0;
                $totalDefectuosos = 0;

                // 1. Crear registro de renovación (ticket)
                $renovacion = CargaDiariaRenovacion::create([
                    'owner_id' => $ownerId,
                    'carga_diaria_id' => $cargaDiaria->id,
                    'fecha' => now()->toDateString(),
                    'tipo' => 'recarga',
                    'notas' => $validated['notas'] ?? null,
                    'ventas_totales' => $validated['ventas_totales'],
                    'devoluciones_totales' => $validated['devoluciones_totales'],
                ]);

                // 2. Crear detalles de la renovación y actualizar productos
                foreach ($validated['productos'] as $prod) {
                    $cantidadBordo = $prod['cantidad_bordo'];
                    $cantidadLlena = $prod['cantidad_llena'];
                    $cantidadVacia = $prod['cantidad_vacia'];
                    $cantidadFaltante = $prod['cantidad_faltante'];
                    $cantidadDefectuosa = $prod['cantidad_defectuosa'];
                    $cantidadVendida = $prod['cantidad_vendida'];
                    $cantidadDevuelta = $prod['cantidad_devuelta'];

                    $totalLlenos += $cantidadLlena;
                    $totalVacios += $cantidadVacia;
                    $totalFaltantes += $cantidadFaltante;
                    $totalDefectuosos += $cantidadDefectuosa;

                    // Crear detalle de renovación
                    CargaDiariaRenovacionDetalle::create([
                        'owner_id' => $ownerId,
                        'renovacion_id' => $renovacion->id,
                        'producto_id' => $prod['producto_id'],
                        'cantidad_bordo' => $cantidadBordo,
                        'cantidad_llena' => $cantidadLlena,
                        'cantidad_vacia' => $cantidadVacia,
                        'cantidad_faltante' => $cantidadFaltante,
                        'cantidad_defectuosa' => $cantidadDefectuosa,
                        'cantidad_vendida' => $cantidadVendida,
                        'cantidad_devuelta' => $cantidadDevuelta,
                    ]);

                    // Actualizar producto de la carga actual
                    $cargaDiaria->productos()
                        ->where('producto_id', $prod['producto_id'])
                        ->update([
                            'cantidad_vendida' => $cantidadVendida,
                            'cantidad_devuelta' => $cantidadDevuelta,
                            'cantidad_llena' => $cantidadLlena,
                            'cantidad_vacia' => $cantidadVacia,
                            'cantidad_faltante' => $cantidadFaltante,
                            'cantidad_defectuosa' => $cantidadDefectuosa,
                        ]);
                }

                // 3. Actualizar totales de la renovación
                $renovacion->update([
                    'total_productos_llenos' => $totalLlenos,
                    'total_productos_vacios' => $totalVacios,
                    'total_productos_faltantes' => $totalFaltantes,
                    'total_productos_defectuosos' => $totalDefectuosos,
                ]);

                // 4. Cerrar la carga actual
                $cargaDiaria->update([
                    'estado' => 'cerrado',
                    'ventas_totales' => $validated['ventas_totales'],
                    'devoluciones_totales' => $validated['devoluciones_totales'],
                ]);

                // 5. Crear nueva carga si se solicita
                if (! empty($validated['crear_nueva_carga'])) {
                    $productosRenovar = array_filter($validated['productos'], fn ($p) => $p['cantidad_llena'] > 0);

                    if (! empty($productosRenovar)) {
                        $nuevaCarga = CargaDiaria::create([
                            'owner_id' => $ownerId,
                            'vehiculo_id' => $cargaDiaria->vehiculo_id,
                            'conductor_id' => $cargaDiaria->conductor_id,
                            'fecha' => now()->toDateString(),
                            'estado' => 'pendiente',
                            'notas' => 'Renovación de carga #'.$cargaDiaria->id,
                        ]);

                        foreach ($productosRenovar as $prod) {
                            if ($prod['cantidad_llena'] > 0) {
                                CargaDiariaProducto::create([
                                    'owner_id' => $ownerId,
                                    'carga_diaria_id' => $nuevaCarga->id,
                                    'producto_id' => $prod['producto_id'],
                                    'cantidad_bordo' => $prod['cantidad_llena'],
                                ]);
                            }
                        }
                    }
                }
            });

            return redirect()->route('cargas-diarias.index')
                ->with('success', 'Recarga registrada y ticket generado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al procesar la recarga: '.$e->getMessage());
        }
    }

    public function renovaciones(CargaDiaria $cargaDiaria): Response
    {
        $cargaDiaria->load(['vehiculo', 'conductor', 'renovaciones.detalles.producto']);

        return Inertia::render('Backend/CargaDiaria/Renovaciones', [
            'carga' => $cargaDiaria,
        ]);
    }

    public function verRenovacion(Request $request, int $renovacionId): Response
    {
        $renovacion = CargaDiariaRenovacion::with(['cargaDiaria.vehiculo', 'cargaDiaria.conductor', 'detalles.producto'])
            ->findOrFail($renovacionId);

        if ($request->header('X-Inertia')) {
            return Inertia::render('Backend/CargaDiaria/TicketRecarga', [
                'renovacion' => $renovacion,
            ]);
        }

        return Inertia::render('Backend/CargaDiaria/TicketRecarga', [
            'renovacion' => $renovacion,
        ]);
    }

    public function confirmarRenovacion(Request $request, CargaDiaria $cargaDiaria): RedirectResponse
    {
        $validated = $request->validate([
            'ventas_totales' => 'required|numeric|min:0',
            'devoluciones_totales' => 'required|numeric|min:0',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.cantidad_bordo' => 'required|integer|min:0',
            'productos.*.cantidad_vendida' => 'required|integer|min:0',
            'productos.*.cantidad_devuelta' => 'required|integer|min:0',
            'productos.*.renovar' => 'nullable|boolean',
        ]);

        try {
            DB::transaction(function () use ($cargaDiaria, $validated) {
                $ownerId = auth()->user()->getOwnerId();

                foreach ($validated['productos'] as $prod) {
                    $cargaDiaria->productos()
                        ->where('producto_id', $prod['producto_id'])
                        ->update([
                            'cantidad_vendida' => $prod['cantidad_vendida'],
                            'cantidad_devuelta' => $prod['cantidad_devuelta'],
                        ]);
                }

                $cargaDiaria->update([
                    'estado' => 'cerrado',
                    'ventas_totales' => $validated['ventas_totales'],
                    'devoluciones_totales' => $validated['devoluciones_totales'],
                ]);

                $productosRenovar = array_filter($validated['productos'], fn ($p) => ! empty($p['renovar']));

                if (! empty($productosRenovar)) {
                    $nuevaCarga = CargaDiaria::create([
                        'owner_id' => $ownerId,
                        'vehiculo_id' => $cargaDiaria->vehiculo_id,
                        'conductor_id' => $cargaDiaria->conductor_id,
                        'fecha' => now()->toDateString(),
                        'estado' => 'pendiente',
                        'notas' => 'Renovación de carga #'.$cargaDiaria->id,
                    ]);

                    foreach ($productosRenovar as $prod) {
                        $cantidadRenovar = max(0, $prod['cantidad_bordo'] - $prod['cantidad_vendida'] - $prod['cantidad_devuelta']);
                        if ($cantidadRenovar > 0) {
                            CargaDiariaProducto::create([
                                'owner_id' => $ownerId,
                                'carga_diaria_id' => $nuevaCarga->id,
                                'producto_id' => $prod['producto_id'],
                                'cantidad_bordo' => $cantidadRenovar,
                            ]);
                        }
                    }
                }
            });

            return redirect()->route('cargas-diarias.index')
                ->with('success', 'Carga cerrada y renovación creada correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al renovar la carga: '.$e->getMessage());
        }
    }
}
