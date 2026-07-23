<?php

use App\Http\Controllers\Backend\CallCenterController;
use App\Http\Controllers\Backend\CampanaController;
use App\Http\Controllers\Backend\CategoriaController;
use App\Http\Controllers\Backend\ClienteController;
use App\Http\Controllers\Backend\OportunidadController;
use App\Http\Controllers\Backend\ProductoController;
use App\Http\Controllers\Backend\ProspectoController;
use App\Http\Controllers\Backend\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:comercial.prospectos.viewAny'])->group(function () {
    Route::get('prospectos/export', [ProspectoController::class, 'exportCsv'])->name('prospectos.export');
    Route::get('prospectos/export-excel', [ProspectoController::class, 'exportExcel'])->name('prospectos.exportExcel');
    Route::post('prospectos/import', [ProspectoController::class, 'importCsv'])->name('prospectos.import');
    Route::post('prospectos/import-excel', [ProspectoController::class, 'importExcel'])->name('prospectos.importExcel');
    Route::resource('prospectos', ProspectoController::class)->except(['create', 'edit'])->middleware('ownership:prospecto');
    Route::patch('prospectos/{prospecto}/estado', [ProspectoController::class, 'updateEstado'])->name('prospectos.updateEstado');
});

Route::middleware(['permission:comercial.oportunidades.viewAny'])->group(function () {
    Route::get('oportunidades/export', [OportunidadController::class, 'exportCsv'])->name('oportunidades.export');
    Route::get('oportunidades/export-excel', [OportunidadController::class, 'exportExcel'])->name('oportunidades.exportExcel');
    Route::post('oportunidades/import', [OportunidadController::class, 'importCsv'])->name('oportunidades.import');
    Route::post('oportunidades/import-excel', [OportunidadController::class, 'importExcel'])->name('oportunidades.importExcel');
    Route::patch('oportunidades/{oportunidad}/etapa', [OportunidadController::class, 'updateEtapa'])->name('oportunidades.updateEtapa');
    Route::resource('oportunidades', OportunidadController::class)->except(['create', 'edit'])->parameters(['oportunidades' => 'oportunidad'])->middleware('ownership:oportunidad');
});

Route::middleware(['permission:comercial.clientes.viewAny'])->group(function () {
    Route::get('clientes/export', [ClienteController::class, 'exportCsv'])->name('clientes.export');
    Route::get('clientes/export-excel', [ClienteController::class, 'exportExcel'])->name('clientes.exportExcel');
    Route::post('clientes/import', [ClienteController::class, 'importCsv'])->name('clientes.import');
    Route::post('clientes/import-excel', [ClienteController::class, 'importExcel'])->name('clientes.importExcel');
    Route::resource('clientes', ClienteController::class)->except(['create', 'edit'])->middleware('ownership:cliente');
});

Route::middleware(['permission:comercial.productos.viewAny'])->group(function () {
    Route::get('productos/export-csv', [ProductoController::class, 'exportCsv'])->name('productos.export-csv');
    Route::get('productos/export-excel', [ProductoController::class, 'exportExcel'])->name('productos.exportExcel');
    Route::post('productos/import', [ProductoController::class, 'importCsv'])->name('productos.import');
    Route::post('productos/import-excel', [ProductoController::class, 'importExcel'])->name('productos.importExcel');
    Route::resource('productos', ProductoController::class)->except(['create', 'edit'])->middleware('ownership:producto');
});

Route::middleware(['permission:comercial.categorias.viewAny'])->group(function () {
    Route::get('categorias/export', [CategoriaController::class, 'exportCsv'])->name('categorias.export');
    Route::get('categorias/export-excel', [CategoriaController::class, 'exportExcel'])->name('categorias.exportExcel');
    Route::post('categorias/import', [CategoriaController::class, 'importCsv'])->name('categorias.import');
    Route::post('categorias/import-excel', [CategoriaController::class, 'importExcel'])->name('categorias.importExcel');
    Route::resource('categorias', CategoriaController::class)->except(['create', 'edit'])->middleware('ownership:categoria');
});

Route::middleware(['permission:comercial.campanas.viewAny'])->group(function () {
    Route::get('campanas/export', [CampanaController::class, 'exportCsv'])->name('campanas.export');
    Route::get('campanas/export-excel', [CampanaController::class, 'exportExcel'])->name('campanas.exportExcel');
    Route::post('campanas/import', [CampanaController::class, 'importCsv'])->name('campanas.import');
    Route::post('campanas/import-excel', [CampanaController::class, 'importExcel'])->name('campanas.importExcel');
    Route::resource('campanas', CampanaController::class)->except(['create', 'edit'])->middleware('ownership:campana');
});

Route::middleware(['permission:comercial.tickets.viewAny'])->group(function () {
    Route::get('tickets/export', [TicketController::class, 'exportCsv'])->name('tickets.export');
    Route::get('tickets/export-excel', [TicketController::class, 'exportExcel'])->name('tickets.exportExcel');
    Route::post('tickets/import', [TicketController::class, 'importCsv'])->name('tickets.import');
    Route::post('tickets/import-excel', [TicketController::class, 'importExcel'])->name('tickets.importExcel');
    Route::resource('tickets', TicketController::class)->except(['create', 'edit'])->middleware('ownership:ticket');
});

Route::middleware(['permission:comercial.call-center.viewAny'])->group(function () {
    Route::get('call-center', [CallCenterController::class, 'index'])->name('call-center.index');
    Route::post('call-center/llamadas', [CallCenterController::class, 'storeLlamada'])->name('call-center.llamadas.store');
    Route::delete('call-center/llamadas/{id}', [CallCenterController::class, 'destroyLlamada'])->name('call-center.llamadas.destroy');
    Route::post('call-center/gestiones', [CallCenterController::class, 'storeGestion'])->name('call-center.gestiones.store');
    Route::post('call-center/programaciones', [CallCenterController::class, 'storeProgramacion'])->name('call-center.programaciones.store');
    Route::delete('call-center/programaciones/{id}', [CallCenterController::class, 'destroyProgramacion'])->name('call-center.programaciones.destroy');
    Route::patch('call-center/programaciones/{id}/completar', [CallCenterController::class, 'completeProgramacion'])->name('call-center.programaciones.completar');
    Route::get('call-center/export', [CallCenterController::class, 'exportCsv'])->name('call-center.export');
    Route::get('call-center/export-excel', [CallCenterController::class, 'exportExcel'])->name('call-center.exportExcel');
    Route::post('call-center/import', [CallCenterController::class, 'importCsv'])->name('call-center.import');
    Route::post('call-center/import-excel', [CallCenterController::class, 'importExcel'])->name('call-center.importExcel');
});
