<?php

use App\Http\Controllers\Backend\CobranzaController;
use App\Http\Controllers\Backend\ContabilidadController;
use App\Http\Controllers\Backend\FacturacionController;
use App\Http\Controllers\Backend\ImpuestoController;
use App\Http\Controllers\Backend\PagoController;
use App\Http\Controllers\Backend\SiiController;
use App\Http\Controllers\Backend\TesoreriaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:finanzas.cobranzas.viewAny'])->group(function () {
    Route::resource('cobranzas', CobranzaController::class)->except(['show', 'create', 'edit'])->middleware('ownership:cobranza');
    Route::get('cobranzas/export', [CobranzaController::class, 'exportCsv'])->name('cobranzas.export');
    Route::get('cobranzas/export-excel', [CobranzaController::class, 'exportExcel'])->name('cobranzas.exportExcel');
    Route::post('cobranzas/import', [CobranzaController::class, 'importCsv'])->name('cobranzas.import');
    Route::post('cobranzas/import-excel', [CobranzaController::class, 'importExcel'])->name('cobranzas.importExcel');
});

Route::middleware(['permission:finanzas.pagos.viewAny'])->group(function () {
    Route::resource('pagos', PagoController::class)->except(['show', 'create', 'edit'])->middleware('ownership:pago');
    Route::get('pagos/export', [PagoController::class, 'exportCsv'])->name('pagos.export');
    Route::get('pagos/export-excel', [PagoController::class, 'exportExcel'])->name('pagos.exportExcel');
    Route::post('pagos/import', [PagoController::class, 'importCsv'])->name('pagos.import');
    Route::post('pagos/import-excel', [PagoController::class, 'importExcel'])->name('pagos.importExcel');
});

Route::middleware(['permission:finanzas.tesoreria.viewAny'])->group(function () {
    Route::resource('tesoreria', TesoreriaController::class)->except(['create', 'show', 'edit'])->middleware('ownership:tesoreria');
});

Route::middleware(['permission:finanzas.contabilidad.viewAny'])->group(function () {
    Route::resource('contabilidad', ContabilidadController::class)->except(['show', 'create', 'edit'])->parameters(['contabilidad' => 'asiento'])->middleware('ownership:asiento');
    Route::get('contabilidad/export', [ContabilidadController::class, 'exportCsv'])->name('contabilidad.export');
    Route::get('contabilidad/export-excel', [ContabilidadController::class, 'exportExcel'])->name('contabilidad.exportExcel');
    Route::post('contabilidad/import', [ContabilidadController::class, 'importCsv'])->name('contabilidad.import');
    Route::post('contabilidad/import-excel', [ContabilidadController::class, 'importExcel'])->name('contabilidad.importExcel');
});

Route::middleware(['permission:finanzas.impuestos.viewAny'])->group(function () {
    Route::resource('impuestos', ImpuestoController::class)->except(['show', 'create', 'edit'])->middleware('ownership:impuesto');
    Route::get('impuestos/export', [ImpuestoController::class, 'exportCsv'])->name('impuestos.export');
    Route::get('impuestos/export-excel', [ImpuestoController::class, 'exportExcel'])->name('impuestos.exportExcel');
    Route::post('impuestos/import', [ImpuestoController::class, 'importCsv'])->name('impuestos.import');
    Route::post('impuestos/import-excel', [ImpuestoController::class, 'importExcel'])->name('impuestos.importExcel');
});

Route::middleware(['permission:finanzas.facturacion.viewAny'])->group(function () {
    Route::resource('facturacion', FacturacionController::class)->except(['show', 'create', 'edit'])->middleware('ownership:factura');
    Route::get('facturacion/export', [FacturacionController::class, 'exportCsv'])->name('facturacion.export');
    Route::get('facturacion/export-excel', [FacturacionController::class, 'exportExcel'])->name('facturacion.exportExcel');
    Route::post('facturacion/import', [FacturacionController::class, 'importCsv'])->name('facturacion.import');
    Route::post('facturacion/import-excel', [FacturacionController::class, 'importExcel'])->name('facturacion.importExcel');
    Route::get('facturacion/{factura}/pdf', [FacturacionController::class, 'downloadPdf'])->name('facturacion.pdf')->middleware('ownership:factura');
});

// SII – Facturación Electrónica
Route::middleware(['permission:finanzas.sii.viewAny'])->group(function () {
    Route::prefix('sii')->name('sii.')->group(function () {
        Route::get('/', [SiiController::class, 'index'])->name('index');
        Route::get('/caf/subir', [SiiController::class, 'createCaf'])->name('caf.create');
        Route::post('/caf', [SiiController::class, 'storeCaf'])->name('caf.store');
        Route::get('/documentos', [SiiController::class, 'documentos'])->name('documentos');
        Route::get('/configuracion', [SiiController::class, 'configuracion'])->name('config');
        Route::post('/configuracion', [SiiController::class, 'saveConfiguracion'])->name('config.save');

        // Rutas específicas de configuración para el Right Sidebar
        Route::get('/configuracion/certificado', [SiiController::class, 'editCertificado'])->name('config.certificado');
        Route::post('/configuracion/certificado', [SiiController::class, 'updateCertificado'])->name('config.certificado.update');
        Route::post('/configuracion/certificado/probar', [SiiController::class, 'probarCertificado'])->name('config.certificado.probar');
        Route::get('/configuracion/emisor', [SiiController::class, 'editEmisor'])->name('config.emisor');
        Route::post('/configuracion/emisor', [SiiController::class, 'updateEmisor'])->name('config.emisor.update');
        Route::get('/configuracion/folios', [SiiController::class, 'editFolios'])->name('config.folios');
        Route::get('/configuracion/ambiente', [SiiController::class, 'editAmbiente'])->name('config.ambiente');
        Route::post('/configuracion/ambiente', [SiiController::class, 'updateAmbiente'])->name('config.ambiente.update');

        Route::post('/token/refrescar', [SiiController::class, 'refrescarToken'])->name('token.refrescar');
    });
});
