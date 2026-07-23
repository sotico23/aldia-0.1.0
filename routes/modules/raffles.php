<?php

use App\Http\Controllers\Backend\RaffleController;
use App\Http\Controllers\RafflePublicController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'permission:rifas.rifas.viewAny'])->group(function () {
    Route::get('raffles/draws', [RaffleController::class, 'drawsIndex'])->name('raffles.draws.index');
    Route::get('raffles/export', [RaffleController::class, 'exportCsv'])->name('raffles.bulk.export');
    Route::get('raffles/export-excel', [RaffleController::class, 'exportExcel'])->name('raffles.bulk.exportExcel');
    Route::post('raffles/import', [RaffleController::class, 'importCsv'])->name('raffles.bulk.import');
    Route::post('raffles/import-excel', [RaffleController::class, 'importExcel'])->name('raffles.bulk.importExcel');
    Route::resource('raffles', RaffleController::class)->middleware('ownership:raffle');

    Route::post('raffles/{raffle}/prizes', [RaffleController::class, 'storePrize'])->name('raffles.prizes.store')->middleware('ownership:raffle');
    Route::put('prizes/{prize}', [RaffleController::class, 'updatePrize'])->name('raffles.prizes.update')->middleware('ownership:prize');
    Route::delete('prizes/{prize}', [RaffleController::class, 'destroyPrize'])->name('raffles.prizes.destroy')->middleware('ownership:prize');

    Route::post('raffles/{raffle}/draw', [RaffleController::class, 'draw'])->name('raffles.draw')->middleware('ownership:raffle');
    Route::get('raffles/{raffle}/export', [RaffleController::class, 'exportParticipants'])->name('raffles.export')->middleware('ownership:raffle');
    Route::get('raffles/{raffle}/draw-room', [RaffleController::class, 'drawRoom'])->name('raffles.draw-room')->middleware('ownership:raffle');
});

// Public routes — some require auth
Route::get('rifa/{slug}', [RafflePublicController::class, 'show'])->name('raffles.public.show');
Route::post('rifa/{slug}/participate', [RafflePublicController::class, 'participate'])->name('raffles.public.participate')->middleware('auth');
Route::post('rifa/{slug}/buy-numbers', [RafflePublicController::class, 'buyNumbers'])->name('raffles.public.buy-numbers')->middleware('auth');
Route::get('rifa/{slug}/ganadores', [RafflePublicController::class, 'winners'])->name('raffles.public.winners');
