<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Empleado;
use App\Models\Prestamo;
use App\Models\PrestamoCuota;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PrestamoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:rrhh.prestamos.create', only: ['store']),
            new Middleware('permission:rrhh.prestamos.edit', only: ['update']),
            new Middleware('permission:rrhh.prestamos.delete', only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $prestamos = Prestamo::with(['empleado'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $empleados = Empleado::select('id', 'nombre', 'apellido', 'rut', 'salario')
            ->orderBy('nombre')
            ->limit(100)
            ->get();

        return Inertia::render('Backend/Prestamos/Index', [
            'prestamos' => $prestamos,
            'empleados' => $empleados,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'empleado_id' => 'required|exists:empleados,id',
            'tipo' => 'required|string|in:prestamo,adelanto',
            'monto_total' => 'required|numeric|min:1',
            'numero_cuotas' => 'required|integer|min:1',
            'frecuencia' => 'required|string|in:semanal,quincenal,mensual',
            'fecha_inicio' => 'required|date',
            'motivo' => 'nullable|string',
            'notas' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($validated) {
                $ownerId = auth()->user()->getOwnerId();

                $montoCuota = round($validated['monto_total'] / $validated['numero_cuotas'], 2);

                // Ajustar última cuota para que cuadre
                $ultimaCuota = $validated['monto_total'] - ($montoCuota * ($validated['numero_cuotas'] - 1));

                $fechaInicio = Carbon::parse($validated['fecha_inicio']);

                // Calcular fecha de fin según frecuencia
                $fechaFin = match ($validated['frecuencia']) {
                    'semanal' => $fechaInicio->copy()->addWeeks($validated['numero_cuotas']),
                    'quincenal' => $fechaInicio->copy()->addWeeks($validated['numero_cuotas'] * 2),
                    'mensual' => $fechaInicio->copy()->addMonths($validated['numero_cuotas']),
                    default => $fechaInicio->copy()->addMonths($validated['numero_cuotas']),
                };

                $prestamo = Prestamo::create([
                    'owner_id' => $ownerId,
                    'empleado_id' => $validated['empleado_id'],
                    'tipo' => $validated['tipo'],
                    'monto_total' => $validated['monto_total'],
                    'monto_cuota' => $montoCuota,
                    'numero_cuotas' => $validated['numero_cuotas'],
                    'cuotas_pagadas' => 0,
                    'saldo_pendiente' => $validated['monto_total'],
                    'fecha_inicio' => $validated['fecha_inicio'],
                    'fecha_fin' => $fechaFin->toDateString(),
                    'frecuencia' => $validated['frecuencia'],
                    'estado' => 'activo',
                    'motivo' => $validated['motivo'] ?? null,
                    'notas' => $validated['notas'] ?? null,
                ]);

                // Generar cuotas
                for ($i = 1; $i <= $validated['numero_cuotas']; $i++) {
                    $monto = $i === $validated['numero_cuotas'] ? $ultimaCuota : $montoCuota;

                    $fechaCuota = match ($validated['frecuencia']) {
                        'semanal' => $fechaInicio->copy()->addWeeks($i - 1),
                        'quincenal' => $fechaInicio->copy()->addWeeks(($i - 1) * 2),
                        'mensual' => $fechaInicio->copy()->addMonths($i - 1),
                        default => $fechaInicio->copy()->addMonths($i - 1),
                    };

                    PrestamoCuota::create([
                        'owner_id' => $ownerId,
                        'prestamo_id' => $prestamo->id,
                        'numero_cuota' => $i,
                        'monto' => $monto,
                        'fecha_vencimiento' => $fechaCuota->toDateString(),
                        'estado' => 'pendiente',
                    ]);
                }
            });

            return redirect()->route('prestamos.index')->with('success', 'Préstamo registrado con éxito.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al registrar el préstamo: '.$e->getMessage());
        }
    }

    public function show(Prestamo $prestamo): Response
    {
        $prestamo->load(['empleado', 'cuotas']);

        return Inertia::render('Backend/Prestamos/Show', [
            'prestamo' => $prestamo,
        ]);
    }

    public function update(Request $request, Prestamo $prestamo): RedirectResponse
    {
        $validated = $request->validate([
            'empleado_id' => 'required|exists:empleados,id',
            'tipo' => 'required|string|in:prestamo,adelanto',
            'monto_total' => 'required|numeric|min:1',
            'numero_cuotas' => 'required|integer|min:1',
            'frecuencia' => 'required|string|in:semanal,quincenal,mensual',
            'fecha_inicio' => 'required|date',
            'motivo' => 'nullable|string',
            'notas' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($prestamo, $validated) {
                $montoCuota = round($validated['monto_total'] / $validated['numero_cuotas'], 2);
                $ultimaCuota = $validated['monto_total'] - ($montoCuota * ($validated['numero_cuotas'] - 1));
                $fechaInicio = Carbon::parse($validated['fecha_inicio']);

                $fechaFin = match ($validated['frecuencia']) {
                    'semanal' => $fechaInicio->copy()->addWeeks($validated['numero_cuotas']),
                    'quincenal' => $fechaInicio->copy()->addWeeks($validated['numero_cuotas'] * 2),
                    'mensual' => $fechaInicio->copy()->addMonths($validated['numero_cuotas']),
                    default => $fechaInicio->copy()->addMonths($validated['numero_cuotas']),
                };

                $prestamo->update([
                    'empleado_id' => $validated['empleado_id'],
                    'tipo' => $validated['tipo'],
                    'monto_total' => $validated['monto_total'],
                    'monto_cuota' => $montoCuota,
                    'numero_cuotas' => $validated['numero_cuotas'],
                    'frecuencia' => $validated['frecuencia'],
                    'fecha_inicio' => $validated['fecha_inicio'],
                    'fecha_fin' => $fechaFin->toDateString(),
                    'motivo' => $validated['motivo'] ?? null,
                    'notas' => $validated['notas'] ?? null,
                ]);

                $prestamo->cuotas()->where('estado', 'pendiente')->delete();

                $existingPaid = $prestamo->cuotas()->where('estado', 'pagada')->count();
                $aCrear = max(0, $validated['numero_cuotas'] - $existingPaid);

                for ($i = 1; $i <= $aCrear; $i++) {
                    $monto = ($existingPaid + $i) === $validated['numero_cuotas'] ? $ultimaCuota : $montoCuota;

                    $fechaCuota = match ($validated['frecuencia']) {
                        'semanal' => $fechaInicio->copy()->addWeeks($existingPaid + $i - 1),
                        'quincenal' => $fechaInicio->copy()->addWeeks(($existingPaid + $i - 1) * 2),
                        'mensual' => $fechaInicio->copy()->addMonths($existingPaid + $i - 1),
                        default => $fechaInicio->copy()->addMonths($existingPaid + $i - 1),
                    };

                    PrestamoCuota::create([
                        'owner_id' => auth()->user()->getOwnerId(),
                        'prestamo_id' => $prestamo->id,
                        'numero_cuota' => $existingPaid + $i,
                        'monto' => $monto,
                        'fecha_vencimiento' => $fechaCuota->toDateString(),
                        'estado' => 'pendiente',
                    ]);
                }

                $cuotasPagadas = $prestamo->cuotas()->where('estado', 'pagada')->count();
                $saldoPendiente = $prestamo->cuotas()->where('estado', '!=', 'pagada')->sum('monto');

                $prestamo->update([
                    'cuotas_pagadas' => $cuotasPagadas,
                    'saldo_pendiente' => $saldoPendiente,
                    'estado' => $saldoPendiente <= 0 ? 'pagado' : 'activo',
                ]);
            });

            return redirect()->route('prestamos.index')->with('success', 'Préstamo actualizado exitosamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al actualizar el préstamo: '.$e->getMessage());
        }
    }

    public function destroy(Prestamo $prestamo): RedirectResponse
    {
        try {
            $prestamo->delete();

            return redirect()->route('prestamos.index')->with('success', 'Préstamo eliminado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al eliminar el préstamo: '.$e->getMessage());
        }
    }

    public function registrarPago(Request $request, PrestamoCuota $cuota): RedirectResponse
    {
        $validated = $request->validate([
            'monto_pagado' => 'required|numeric|min:0.01',
            'fecha_pago' => 'required|date',
            'metodo_pago' => 'required|string|in:efectivo,transferencia,nomina',
            'referencia_pago' => 'nullable|string',
            'notas' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($cuota, $validated) {
                $cuota->update([
                    'monto_pagado' => $validated['monto_pagado'],
                    'fecha_pago' => $validated['fecha_pago'],
                    'metodo_pago' => $validated['metodo_pago'],
                    'referencia_pago' => $validated['referencia_pago'] ?? null,
                    'estado' => 'pagada',
                    'notas' => $validated['notas'] ?? null,
                ]);

                // Actualizar préstamo
                $prestamo = $cuota->prestamo;
                $cuotasPagadas = $prestamo->cuotas()->where('estado', 'pagada')->count();
                $saldoPendiente = $prestamo->cuotas()->where('estado', '!=', 'pagada')->sum('monto');

                $prestamo->update([
                    'cuotas_pagadas' => $cuotasPagadas,
                    'saldo_pendiente' => $saldoPendiente,
                    'estado' => $cuotasPagadas >= $prestamo->numero_cuotas ? 'pagado' : 'activo',
                ]);
            });

            return redirect()->back()->with('success', 'Pago registrado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al registrar el pago: '.$e->getMessage());
        }
    }

    public function aplicarNomina(Request $request, PrestamoCuota $cuota): RedirectResponse
    {
        $validated = $request->validate([
            'nomina_periodo' => 'required|string',
        ]);

        try {
            $cuota->update([
                'aplicada_en_nomina' => true,
                'nomina_periodo' => $validated['nomina_periodo'],
                'metodo_pago' => 'nomina',
                'estado' => 'pagada',
                'fecha_pago' => now()->toDateString(),
                'monto_pagado' => $cuota->monto,
            ]);

            // Actualizar préstamo
            $prestamo = $cuota->prestamo;
            $cuotasPagadas = $prestamo->cuotas()->where('estado', 'pagada')->count();
            $saldoPendiente = $prestamo->cuotas()->where('estado', '!=', 'pagada')->sum('monto');

            $prestamo->update([
                'cuotas_pagadas' => $cuotasPagadas,
                'saldo_pendiente' => $saldoPendiente,
                'estado' => $cuotasPagadas >= $prestamo->numero_cuotas ? 'pagado' : 'activo',
            ]);

            return redirect()->back()->with('success', 'Cuota aplicada en nómina correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al aplicar en nómina: '.$e->getMessage());
        }
    }

    public function agregarCuotas(Request $request, Prestamo $prestamo): RedirectResponse
    {
        $validated = $request->validate([
            'numero_cuotas' => 'required|integer|min:1|max:48',
        ]);

        try {
            $pendientes = $prestamo->cuotas()->where('estado', 'pendiente')->count();

            if ($pendientes <= 0) {
                return redirect()->back()->with('error', 'El préstamo ya está completamente pagado. Use editar para gestionar cuotas extras.');
            }

            DB::transaction(function () use ($prestamo, $validated, $pendientes) {
                $aPagar = min($validated['numero_cuotas'], $pendientes);

                $prestamo->cuotas()
                    ->where('estado', 'pendiente')
                    ->oldest('numero_cuota')
                    ->limit($aPagar)
                    ->update([
                        'estado' => 'pagada',
                        'fecha_pago' => now()->toDateString(),
                        'monto_pagado' => DB::raw('monto'),
                    ]);

                $cuotasPagadas = $prestamo->cuotas()->where('estado', 'pagada')->count();
                $saldoPendiente = $prestamo->cuotas()->where('estado', '!=', 'pagada')->sum('monto');

                $prestamo->update([
                    'cuotas_pagadas' => $cuotasPagadas,
                    'saldo_pendiente' => $saldoPendiente,
                    'estado' => $saldoPendiente <= 0 ? 'pagado' : 'activo',
                ]);
            });

            $pagadas = min($validated['numero_cuotas'], $pendientes);
            $msg = $pagadas > 0
                ? "{$pagadas} cuota(s) pagada(s) correctamente."
                : 'No habían cuotas pendientes.';

            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al agregar cuotas: '.$e->getMessage());
        }
    }

    public function cuotasPendientes(Request $request): Response
    {
        $cuotas = PrestamoCuota::with(['prestamo.empleado'])
            ->where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<=', now()->copy()->endOfMonth())
            ->orderBy('fecha_vencimiento')
            ->get();

        $empleados = Empleado::select('id', 'nombre', 'apellido')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Backend/Prestamos/CuotasPendientes', [
            'cuotas' => $cuotas,
            'empleados' => $empleados,
        ]);
    }
}
