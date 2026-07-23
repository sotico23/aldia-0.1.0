<?php

use App\Http\Controllers\Backend\AlmacenController;
use App\Http\Controllers\Backend\CompraController;
use App\Http\Controllers\Backend\InventarioCierreController;
use App\Http\Controllers\Backend\InventarioController;
use App\Http\Controllers\Backend\LoteController;
use App\Http\Controllers\Backend\MovimientoController;
use App\Http\Controllers\Backend\ProveedorController;
use App\Http\Controllers\Backend\VacioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:inventario.proveedores.viewAny'])->group(function () {
    Route::get('proveedors/export', [ProveedorController::class, 'exportCsv'])->name('proveedors.export');
    Route::get('proveedors/export-excel', [ProveedorController::class, 'exportExcel'])->name('proveedors.exportExcel');
    Route::post('proveedors/import', [ProveedorController::class, 'importCsv'])->name('proveedors.import');
    Route::post('proveedors/import-excel', [ProveedorController::class, 'importExcel'])->name('proveedors.importExcel');
    Route::resource('proveedors', ProveedorController::class)->except(['create', 'edit'])->middleware('ownership:proveedor');
});

Route::middleware(['permission:inventario.compras.viewAny'])->group(function () {
    Route::get('compras/export', [CompraController::class, 'exportCsv'])->name('compras.export');
    Route::get('compras/export-excel', [CompraController::class, 'exportExcel'])->name('compras.exportExcel');
    Route::post('compras/import', [CompraController::class, 'importCsv'])->name('compras.import');
    Route::post('compras/import-excel', [CompraController::class, 'importExcel'])->name('compras.importExcel');
    Route::get('compras/{compra}/pdf', [CompraController::class, 'downloadPdf'])->name('compras.pdf');
    Route::resource('compras', CompraController::class)->except(['create', 'show', 'edit'])->middleware('ownership:compra');
});

Route::middleware(['permission:inventario.inventarios.viewAny'])->group(function () {
    Route::get('inventarios/export', [InventarioController::class, 'exportCsv'])->name('inventarios.export');
    Route::get('inventarios/export-excel', [InventarioController::class, 'exportExcel'])->name('inventarios.exportExcel');
    Route::post('inventarios/import', [InventarioController::class, 'importCsv'])->name('inventarios.import');
    Route::post('inventarios/import-excel', [InventarioController::class, 'importExcel'])->name('inventarios.importExcel');
    Route::resource('inventarios', InventarioController::class)->except(['create', 'edit'])->middleware('ownership:inventario');
});

Route::middleware(['permission:inventario.almacenes.viewAny'])->group(function () {
    Route::get('almacenes/export', [AlmacenController::class, 'exportCsv'])->name('almacenes.export');
    Route::get('almacenes/export-excel', [AlmacenController::class, 'exportExcel'])->name('almacenes.exportExcel');
    Route::post('almacenes/import', [AlmacenController::class, 'importCsv'])->name('almacenes.import');
    Route::post('almacenes/import-excel', [AlmacenController::class, 'importExcel'])->name('almacenes.importExcel');
    Route::resource('almacenes', AlmacenController::class)->except(['create', 'edit'])->middleware('ownership:almacen');
});

Route::middleware(['permission:inventario.lotes.viewAny'])->group(function () {
    Route::get('lotes/export', [LoteController::class, 'exportCsv'])->name('lotes.export');
    Route::get('lotes/export-excel', [LoteController::class, 'exportExcel'])->name('lotes.exportExcel');
    Route::post('lotes/import', [LoteController::class, 'importCsv'])->name('lotes.import');
    Route::post('lotes/import-excel', [LoteController::class, 'importExcel'])->name('lotes.importExcel');
    Route::resource('lotes', LoteController::class)->except(['create', 'show', 'edit'])->middleware('ownership:lote');
});

Route::middleware(['permission:inventario.movimientos.viewAny'])->group(function () {
    Route::get('movimientos/export', [MovimientoController::class, 'exportCsv'])->name('movimientos.export');
    Route::get('movimientos/export-excel', [MovimientoController::class, 'exportExcel'])->name('movimientos.exportExcel');
    Route::post('movimientos/import', [MovimientoController::class, 'importCsv'])->name('movimientos.import');
    Route::post('movimientos/import-excel', [MovimientoController::class, 'importExcel'])->name('movimientos.importExcel');
    Route::resource('movimientos', MovimientoController::class)->except(['create', 'show', 'edit'])->middleware('ownership:movimiento');
});

Route::middleware(['permission:inventario.vacios.viewAny'])->group(function () {
    Route::get('vacios/export', [VacioController::class, 'exportCsv'])->name('vacios.export');
    Route::get('vacios/export-excel', [VacioController::class, 'exportExcel'])->name('vacios.exportExcel');
    Route::post('vacios/import', [VacioController::class, 'importCsv'])->name('vacios.import');
    Route::post('vacios/import-excel', [VacioController::class, 'importExcel'])->name('vacios.importExcel');
    Route::resource('vacios', VacioController::class)->except(['create', 'show', 'edit'])->middleware('ownership:vacio');
    Route::patch('vacios/{vacio}/retornar', [VacioController::class, 'retornar'])->name('vacios.retornar')->middleware('ownership:vacio');
});

Route::middleware(['permission:inventario.inventarios.viewAny'])->group(function () {
    Route::get('inventario-cierre', [InventarioCierreController::class, 'index'])->name('inventario-cierre.index');
    Route::get('inventario-cierre/create', [InventarioCierreController::class, 'create'])->name('inventario-cierre.create');
    Route::post('inventario-cierre', [InventarioCierreController::class, 'store'])->name('inventario-cierre.store');
    Route::get('inventario-cierre/{cierre}', [InventarioCierreController::class, 'show'])->name('inventario-cierre.show')->middleware('ownership:cierre');
    Route::patch('inventario-cierre/{cierre}', [InventarioCierreController::class, 'update'])->name('inventario-cierre.update')->middleware('ownership:cierre');
    Route::patch('inventario-cierre/{cierre}/audit', [InventarioCierreController::class, 'audit'])->name('inventario-cierre.audit')->middleware('ownership:cierre');
    Route::delete('inventario-cierre/{cierre}', [InventarioCierreController::class, 'destroy'])->name('inventario-cierre.destroy')->middleware('ownership:cierre');
    Route::get('inventario-cierre/export', [InventarioCierreController::class, 'exportCsv'])->name('inventario-cierre.export');
    Route::get('inventario-cierre/export-excel', [InventarioCierreController::class, 'exportExcel'])->name('inventario-cierre.exportExcel');
});
