<?php

use App\Http\Controllers\Backend\AsistenciaController;
use App\Http\Controllers\Backend\EmpleadoController;
use App\Http\Controllers\Backend\EvaluacionController;
use App\Http\Controllers\Backend\NominaController;
use App\Http\Controllers\Backend\PrestamoController;
use App\Http\Controllers\Backend\ReclutamientoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:rrhh.empleados.viewAny'])->group(function () {
    Route::get('empleados/export', [EmpleadoController::class, 'exportCsv'])->name('empleados.export');
    Route::get('empleados/export-excel', [EmpleadoController::class, 'exportExcel'])->name('empleados.exportExcel');
    Route::post('empleados/import', [EmpleadoController::class, 'importCsv'])->name('empleados.import');
    Route::post('empleados/import-excel', [EmpleadoController::class, 'importExcel'])->name('empleados.importExcel');
    Route::resource('empleados', EmpleadoController::class)->except(['create', 'show', 'edit'])->middleware('ownership:empleado');
});

Route::middleware(['permission:rrhh.nominas.viewAny'])->group(function () {
    Route::get('nominas/export', [NominaController::class, 'exportCsv'])->name('nominas.export');
    Route::get('nominas/export-excel', [NominaController::class, 'exportExcel'])->name('nominas.exportExcel');
    Route::post('nominas/import', [NominaController::class, 'importCsv'])->name('nominas.import');
    Route::post('nominas/import-excel', [NominaController::class, 'importExcel'])->name('nominas.importExcel');
    Route::get('nominas/calcular', [NominaController::class, 'calcularProporcional'])->name('nominas.calcular');
    Route::resource('nominas', NominaController::class)->except(['create', 'show', 'edit'])->middleware('ownership:nomina');
});

Route::middleware(['permission:rrhh.asistencia.viewAny'])->group(function () {
    Route::get('asistencia/export', [AsistenciaController::class, 'exportCsv'])->name('asistencia.export');
    Route::get('asistencia/export-excel', [AsistenciaController::class, 'exportExcel'])->name('asistencia.exportExcel');
    Route::post('asistencia/import', [AsistenciaController::class, 'importCsv'])->name('asistencia.import');
    Route::post('asistencia/import-excel', [AsistenciaController::class, 'importExcel'])->name('asistencia.importExcel');
    Route::resource('asistencia', AsistenciaController::class)->except(['create', 'show', 'edit'])->parameters([
        'asistencia' => 'asistencia',
    ])->middleware('ownership:asistencia');
});

Route::middleware(['permission:rrhh.reclutamiento.viewAny'])->group(function () {
    Route::get('reclutamiento/export', [ReclutamientoController::class, 'exportCsv'])->name('reclutamiento.export');
    Route::get('reclutamiento/export-excel', [ReclutamientoController::class, 'exportExcel'])->name('reclutamiento.exportExcel');
    Route::post('reclutamiento/import', [ReclutamientoController::class, 'importCsv'])->name('reclutamiento.import');
    Route::post('reclutamiento/import-excel', [ReclutamientoController::class, 'importExcel'])->name('reclutamiento.importExcel');
    Route::resource('reclutamiento', ReclutamientoController::class)->except(['create', 'show', 'edit'])->middleware('ownership:reclutamiento');
});

Route::middleware(['permission:rrhh.evaluaciones.viewAny'])->group(function () {
    Route::get('evaluaciones/export', [EvaluacionController::class, 'exportCsv'])->name('evaluaciones.export');
    Route::get('evaluaciones/export-excel', [EvaluacionController::class, 'exportExcel'])->name('evaluaciones.exportExcel');
    Route::post('evaluaciones/import', [EvaluacionController::class, 'importCsv'])->name('evaluaciones.import');
    Route::post('evaluaciones/import-excel', [EvaluacionController::class, 'importExcel'])->name('evaluaciones.importExcel');
    Route::resource('evaluaciones', EvaluacionController::class)->except(['create', 'show', 'edit'])->middleware('ownership:evaluacione');
});

Route::middleware(['permission:rrhh.prestamos.viewAny'])->group(function () {
    Route::get('prestamos/cuotas-pendientes', [PrestamoController::class, 'cuotasPendientes'])->name('prestamos.cuotas-pendientes');
    Route::resource('prestamos', PrestamoController::class)->except(['edit'])->parameters(['prestamos' => 'prestamo'])->middleware('ownership:prestamo');
    Route::post('prestamo-cuotas/{cuota}/pagar', [PrestamoController::class, 'registrarPago'])->name('prestamo-cuotas.pagar')->middleware('ownership:cuota');
    Route::post('prestamo-cuotas/{cuota}/aplicar-nomina', [PrestamoController::class, 'aplicarNomina'])->name('prestamo-cuotas.aplicar-nomina')->middleware('ownership:cuota');
    Route::post('prestamos/{prestamo}/cuotas', [PrestamoController::class, 'agregarCuotas'])->name('prestamos.cuotas.agregar')->middleware('ownership:prestamo');
});
