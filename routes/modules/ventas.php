<?php

use App\Http\Controllers\Backend\CuponController;
use App\Http\Controllers\Backend\PosController;
use App\Http\Controllers\Backend\VarianteController;
use App\Http\Controllers\Backend\VentaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:ventas.ventas.viewAny'])->group(function () {
    Route::resource('ventas', VentaController::class)->except(['create', 'show', 'edit'])->middleware('ownership:venta');
    Route::patch('ventas/{venta}/status', [VentaController::class, 'updateStatus'])->name('ventas.status');
    Route::get('ventas/export', [VentaController::class, 'exportCsv'])->name('ventas.export');
    Route::get('ventas/export-excel', [VentaController::class, 'exportExcel'])->name('ventas.export_excel');
    Route::post('ventas/import', [VentaController::class, 'importCsv'])->name('ventas.import');
    Route::post('ventas/import-excel', [VentaController::class, 'importExcel'])->name('ventas.import_excel');
});

Route::middleware(['permission:ventas.pos.viewAny'])->group(function () {
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::post('/', [PosController::class, 'store'])->name('store');
        Route::post('{venta}/emitir-dte', [PosController::class, 'emitirDte'])->name('emitir-dte');
        Route::get('cierre', [PosController::class, 'cierreCaja'])->name('cierre');
        Route::post('cierre/cerrar', [PosController::class, 'cerrarTurno'])->name('cierre.cerrar');
        Route::get('cierre/pdf', [PosController::class, 'exportarArqueoPdf'])->name('cierre.pdf');
        Route::get('cierre/csv', [PosController::class, 'exportarArqueoCsv'])->name('cierre.csv');
        Route::get('facturacion', [PosController::class, 'facturacion'])->name('facturacion');
        Route::get('promociones', [PosController::class, 'promociones'])->name('promociones');
        Route::post('promociones', [PosController::class, 'storePromocion'])->name('promociones.store');
        Route::patch('promociones/{promocion}/toggle', [PosController::class, 'togglePromocion'])->name('promociones.toggle');
        Route::put('promociones/{promocion}', [PosController::class, 'updatePromocion'])->name('promociones.update');
        Route::delete('promociones/{promocion}', [PosController::class, 'destroyPromocion'])->name('promociones.destroy');
        Route::get('reportes', [PosController::class, 'reportes'])->name('reportes');
        Route::get('reportes/exportar', [PosController::class, 'exportarReportes'])->name('reportes.exportar');
        Route::post('reportes/importar', [PosController::class, 'importarReportes'])->name('reportes.importar');
    });
});

Route::middleware(['permission:ventas.variantes.viewAny'])->group(function () {
    Route::prefix('pos')->name('pos.')->group(function () {
        Route::get('variantes', [VarianteController::class, 'index'])->name('variantes');
        Route::post('variantes', [VarianteController::class, 'store'])->name('variantes.store');
        Route::put('variantes/{variante}', [VarianteController::class, 'update'])->name('variantes.update');
        Route::delete('variantes/{variante}', [VarianteController::class, 'destroy'])->name('variantes.destroy');
        Route::get('skus', [VarianteController::class, 'skuIndex'])->name('skus');
        Route::post('skus', [VarianteController::class, 'skuStore'])->name('skus.store');
        Route::delete('skus/{sku}', [VarianteController::class, 'skuDestroy'])->name('skus.destroy');
    });
});

// Cupones — gestión CRUD
Route::middleware(['permission:ventas.cupones.viewAny'])->prefix('cupones')->name('ventas.cupones.')->group(function () {
    Route::get('/', [CuponController::class, 'index'])->name('index');
    Route::post('/', [CuponController::class, 'store'])->name('store')->middleware('permission:ventas.cupones.create');
    Route::put('{cupon}', [CuponController::class, 'update'])->name('update')->middleware('permission:ventas.cupones.edit');
    Route::delete('{cupon}', [CuponController::class, 'destroy'])->name('destroy')->middleware('permission:ventas.cupones.delete');
    Route::patch('{cupon}/toggle', [CuponController::class, 'toggle'])->name('toggle')->middleware('permission:ventas.cupones.edit');
    Route::get('{cupon}/preview', [CuponController::class, 'preview'])->name('preview')->withoutMiddleware('permission:ventas.cupones.viewAny');
});

// Validación pública de cupón (autenticado, sin permiso específico)
Route::post('validar-cupon', [CuponController::class, 'validar'])->name('cupones.validar')->middleware('throttle:cupon-validate');
