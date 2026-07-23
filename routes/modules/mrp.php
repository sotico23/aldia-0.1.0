<?php

use App\Http\Controllers\Backend\BomController;
use App\Http\Controllers\Backend\ControlCalidadController;
use App\Http\Controllers\Backend\OrdenProduccionController;
use App\Http\Controllers\Backend\PlanificacionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:mrp.boms.viewAny'])->group(function () {
    Route::resource('boms', BomController::class)->except(['create', 'show', 'edit'])->middleware('ownership:bom');
});

Route::middleware(['permission:mrp.produccion.viewAny'])->group(function () {
    Route::get('ordenes-produccion/export', [OrdenProduccionController::class, 'exportCsv'])->name('ordenes-produccion.export');
    Route::get('ordenes-produccion/export-excel', [OrdenProduccionController::class, 'exportExcel'])->name('ordenes-produccion.exportExcel');
    Route::post('ordenes-produccion/import', [OrdenProduccionController::class, 'importCsv'])->name('ordenes-produccion.import');
    Route::post('ordenes-produccion/import-excel', [OrdenProduccionController::class, 'importExcel'])->name('ordenes-produccion.importExcel');
    Route::resource('ordenes-produccion', OrdenProduccionController::class)->except(['create', 'show', 'edit'])->middleware('ownership:ordenProduccion');
});

Route::middleware(['permission:mrp.calidad.viewAny'])->group(function () {
    Route::get('calidad/export', [ControlCalidadController::class, 'exportCsv'])->name('calidad.export');
    Route::get('calidad/export-excel', [ControlCalidadController::class, 'exportExcel'])->name('calidad.exportExcel');
    Route::post('calidad/import', [ControlCalidadController::class, 'importCsv'])->name('calidad.import');
    Route::post('calidad/import-excel', [ControlCalidadController::class, 'importExcel'])->name('calidad.importExcel');
    Route::resource('calidad', ControlCalidadController::class)->except(['create', 'show', 'edit'])->middleware('ownership:calidad');
});

Route::middleware(['permission:mrp.planificacion.viewAny'])->group(function () {
    Route::get('planificacion/export', [PlanificacionController::class, 'exportCsv'])->name('planificacion.export');
    Route::get('planificacion/export-excel', [PlanificacionController::class, 'exportExcel'])->name('planificacion.exportExcel');
    Route::post('planificacion/import', [PlanificacionController::class, 'importCsv'])->name('planificacion.import');
    Route::post('planificacion/import-excel', [PlanificacionController::class, 'importExcel'])->name('planificacion.importExcel');
    Route::resource('planificacion', PlanificacionController::class)->except(['create', 'show', 'edit'])->middleware('ownership:planificacion');
});
