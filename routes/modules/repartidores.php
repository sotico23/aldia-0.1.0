<?php

use App\Http\Controllers\Backend\RepartidorController;

Route::middleware(['permission:delivery.repartidores.viewAny'])->group(function () {
    Route::resource('repartidores', RepartidorController::class)
        ->except(['show', 'create', 'edit', 'destroy'])
        ->parameters(['repartidores' => 'repartidor'])
        ->middleware('ownership:repartidor');
});
