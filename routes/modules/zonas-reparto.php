<?php

use App\Http\Controllers\Backend\ZonaRepartoController;

Route::middleware(['permission:delivery.zonas-reparto.viewAny'])->group(function () {
    Route::resource('zonas-reparto', ZonaRepartoController::class)
        ->except(['show', 'create', 'edit'])
        ->parameters(['zonas-reparto' => 'zona'])
        ->middleware('ownership:zona');
});
