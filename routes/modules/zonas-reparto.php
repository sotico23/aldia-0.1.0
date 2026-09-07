<?php

use App\Http\Controllers\Backend\ZonaRepartoController;

Route::middleware(['permission:zonas-reparto.view'])->group(function () {
    Route::resource('zonas-reparto', ZonaRepartoController::class)
        ->except(['show', 'create', 'edit'])
        ->parameters(['zonas-reparto' => 'zona'])
        ->middleware('ownership:zona');
});
