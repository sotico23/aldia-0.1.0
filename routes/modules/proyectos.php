<?php

use App\Http\Controllers\Backend\GastoProyectoController;
use App\Http\Controllers\Backend\HitoController;
use App\Http\Controllers\Backend\ProyectoController;
use App\Http\Controllers\Backend\TimesheetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:proyectos.proyectos.viewAny'])->group(function () {
    Route::resource('proyectos', ProyectoController::class)->except(['create', 'show', 'edit'])->middleware('ownership:proyecto');
    Route::get('proyectos/export', [ProyectoController::class, 'exportCsv'])->name('proyectos.export');
    Route::get('proyectos/export-excel', [ProyectoController::class, 'exportExcel'])->name('proyectos.exportExcel');
    Route::post('proyectos/import', [ProyectoController::class, 'importCsv'])->name('proyectos.import');
    Route::post('proyectos/import-excel', [ProyectoController::class, 'importExcel'])->name('proyectos.importExcel');
});

Route::middleware(['permission:proyectos.hitos.viewAny'])->group(function () {
    Route::resource('hitos', HitoController::class)->except(['show', 'create', 'edit'])->middleware('ownership:hito');
    Route::get('hitos/export', [HitoController::class, 'exportCsv'])->name('hitos.export');
    Route::get('hitos/export-excel', [HitoController::class, 'exportExcel'])->name('hitos.exportExcel');
    Route::post('hitos/import', [HitoController::class, 'importCsv'])->name('hitos.import');
    Route::post('hitos/import-excel', [HitoController::class, 'importExcel'])->name('hitos.importExcel');
});

Route::middleware(['permission:proyectos.timesheets.viewAny'])->group(function () {
    Route::resource('timesheets', TimesheetController::class)->except(['show', 'create', 'edit'])->middleware('ownership:timesheet');
    Route::get('timesheets/export', [TimesheetController::class, 'exportCsv'])->name('timesheets.export');
    Route::get('timesheets/export-excel', [TimesheetController::class, 'exportExcel'])->name('timesheets.exportExcel');
    Route::post('timesheets/import', [TimesheetController::class, 'importCsv'])->name('timesheets.import');
    Route::post('timesheets/import-excel', [TimesheetController::class, 'importExcel'])->name('timesheets.importExcel');
});

Route::middleware(['permission:proyectos.gastos.viewAny'])->group(function () {
    Route::resource('gastos-proyecto', GastoProyectoController::class)->except(['show', 'create', 'edit'])->middleware('ownership:gastoProyecto');
    Route::get('gastos-proyecto/export', [GastoProyectoController::class, 'exportCsv'])->name('gastos-proyecto.export');
    Route::get('gastos-proyecto/export-excel', [GastoProyectoController::class, 'exportExcel'])->name('gastos-proyecto.exportExcel');
    Route::post('gastos-proyecto/import', [GastoProyectoController::class, 'importCsv'])->name('gastos-proyecto.import');
    Route::post('gastos-proyecto/import-excel', [GastoProyectoController::class, 'importExcel'])->name('gastos-proyecto.importExcel');
});
