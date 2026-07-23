<?php

use App\Http\Controllers\Backend\UptimeAlertController;
use App\Http\Controllers\Backend\UptimeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['permission:uptime.alertas.viewAny'])->group(function () {
    Route::resource('uptime/alerts', UptimeAlertController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('ownership:alert');
});

Route::middleware(['permission:uptime.monitores.viewAny'])->group(function () {
    Route::get('uptime', [UptimeController::class, 'index'])->name('uptime.index');
    Route::post('uptime', [UptimeController::class, 'store'])->name('uptime.store');
    Route::get('uptime/{site}', [UptimeController::class, 'show'])->name('uptime.show')->middleware('ownership:site');
    Route::put('uptime/{site}', [UptimeController::class, 'update'])->name('uptime.update')->middleware('ownership:site');
    Route::delete('uptime/{site}', [UptimeController::class, 'destroy'])->name('uptime.destroy')->middleware('ownership:site');
    Route::post('uptime/{site}/check', [UptimeController::class, 'checkNow'])->name('uptime.check')->middleware('ownership:site');
    Route::get('uptime/{site}/incidents', [UptimeController::class, 'incidents'])->name('uptime.incidents')->middleware('ownership:site');
    Route::get('uptime/{site}/export', [UptimeController::class, 'exportCsv'])->name('uptime.export')->middleware('ownership:site');
});
