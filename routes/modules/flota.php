<?php

use App\Http\Controllers\Backend\CargaDiariaController;
use App\Http\Controllers\Backend\ConductorController;
use App\Http\Controllers\Backend\EntregaController;
use App\Http\Controllers\Backend\GrupoTrabajoController;
use App\Http\Controllers\Backend\GrupoTrabajoRendimientoController;
use App\Http\Controllers\Backend\ListaPendientesController;
use App\Http\Controllers\Backend\VehiculoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:flota.vehiculos.viewAny'])->group(function () {
    Route::get('vehiculos/export', [VehiculoController::class, 'exportCsv'])->name('vehiculos.export');
    Route::get('vehiculos/export-excel', [VehiculoController::class, 'exportExcel'])->name('vehiculos.exportExcel');
    Route::post('vehiculos/import', [VehiculoController::class, 'importCsv'])->name('vehiculos.import');
    Route::post('vehiculos/import-excel', [VehiculoController::class, 'importExcel'])->name('vehiculos.importExcel');
    Route::resource('vehiculos', VehiculoController::class)->except(['show', 'create', 'edit'])->middleware('ownership:vehiculo');
    Route::patch('vehiculos/{vehiculo}/tracking', [VehiculoController::class, 'actualizarTracking'])->name('vehiculos.tracking')->middleware('ownership:vehiculo');
    Route::post('vehiculos/{vehiculo}/simular', [VehiculoController::class, 'simularTracking'])->name('vehiculos.simular')->middleware('ownership:vehiculo');
    Route::post('vehiculos/{vehiculo}/limpiar', [VehiculoController::class, 'limpiarTracking'])->name('vehiculos.limpiar')->middleware('ownership:vehiculo');
});

Route::middleware(['permission:flota.conductores.viewAny'])->group(function () {
    Route::resource('conductores', ConductorController::class)->except(['show', 'create', 'edit'])->middleware('ownership:conductor');
    Route::get('conductores/export', [ConductorController::class, 'exportCsv'])->name('conductores.export');
    Route::get('conductores/export-excel', [ConductorController::class, 'exportExcel'])->name('conductores.exportExcel');
    Route::post('conductores/import', [ConductorController::class, 'importCsv'])->name('conductores.import');
    Route::post('conductores/import-excel', [ConductorController::class, 'importExcel'])->name('conductores.importExcel');
    Route::post('conductores/{conductor}/simular', [ConductorController::class, 'simularTracking'])->name('conductores.simular')->middleware('ownership:conductor');
    Route::post('conductores/{conductor}/limpiar', [ConductorController::class, 'limpiarTracking'])->name('conductores.limpiar')->middleware('ownership:conductor');
});

Route::middleware(['permission:flota.entregas.viewAny'])->group(function () {
    Route::get('entregas/export', [EntregaController::class, 'exportCsv'])->name('entregas.export');
    Route::get('entregas/export-excel', [EntregaController::class, 'exportExcel'])->name('entregas.exportExcel');
    Route::post('entregas/import', [EntregaController::class, 'importCsv'])->name('entregas.import');
    Route::post('entregas/import-excel', [EntregaController::class, 'importExcel'])->name('entregas.importExcel');
    Route::resource('entregas', EntregaController::class)->except(['create', 'show', 'edit'])->middleware('ownership:entrega');
});

Route::middleware(['permission:flota.cargas.viewAny'])->group(function () {
    Route::resource('cargas-diarias', CargaDiariaController::class)->except(['create', 'edit'])->parameters(['cargas-diarias' => 'cargaDiaria'])->middleware('ownership:cargaDiaria');
    Route::post('cargas-diarias/{cargaDiaria}/renovar', [CargaDiariaController::class, 'confirmarRenovacion'])->name('cargas-diarias.renovar')->middleware('ownership:cargaDiaria');
    Route::post('cargas-diarias/{cargaDiaria}/recargar', [CargaDiariaController::class, 'recargar'])->name('cargas-diarias.recargar')->middleware('ownership:cargaDiaria');
    Route::get('cargas-diarias/{cargaDiaria}/renovaciones', [CargaDiariaController::class, 'renovaciones'])->name('cargas-diarias.renovaciones')->middleware('ownership:cargaDiaria');
    Route::get('cargas-diarias/renovacion/{renovacionId}', [CargaDiariaController::class, 'verRenovacion'])->name('cargas-diarias.ver-renovacion');
});

Route::middleware(['permission:flota.grupos-trabajo.viewAny'])->group(function () {
    Route::get('grupos-trabajo/export', [GrupoTrabajoController::class, 'exportCsv'])->name('grupos-trabajo.export');
    Route::get('grupos-trabajo/export-excel', [GrupoTrabajoController::class, 'exportExcel'])->name('grupos-trabajo.exportExcel');
    Route::post('grupos-trabajo/import', [GrupoTrabajoController::class, 'importCsv'])->name('grupos-trabajo.import');
    Route::post('grupos-trabajo/import-excel', [GrupoTrabajoController::class, 'importExcel'])->name('grupos-trabajo.importExcel');
    Route::resource('grupos-trabajo', GrupoTrabajoController::class)->except(['create', 'show', 'edit'])->parameters(['grupos-trabajo' => 'grupoTrabajo'])->middleware('ownership:grupoTrabajo');

    Route::prefix('grupos-trabajo')->name('grupos-trabajo.')->group(function () {
        Route::get('rendimiento', [GrupoTrabajoRendimientoController::class, 'index'])->name('rendimiento.index');
        Route::post('rendimiento', [GrupoTrabajoRendimientoController::class, 'store'])->name('rendimiento.store');
        Route::put('rendimiento/{asignacion}', [GrupoTrabajoRendimientoController::class, 'update'])->name('rendimiento.update');
        Route::delete('rendimiento/{asignacion}', [GrupoTrabajoRendimientoController::class, 'destroy'])->name('rendimiento.destroy');
    });
});

Route::get('lista-pendientes', [ListaPendientesController::class, 'index'])->name('lista-pendientes.index')->middleware('permission:flota.grupos-trabajo.viewAny');
