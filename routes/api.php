<?php

use Illuminate\Support\Facades\Route;
use Maestrodimateo\Workflow\Controllers\BasketController;
use Maestrodimateo\Workflow\Controllers\CircuitController;
use Maestrodimateo\Workflow\Controllers\MessageController;

Route::name('workflow.')->group(function (): void {

    // Circuits
    Route::prefix('circuits')->name('circuits.')->group(function (): void {
        Route::get('/', [CircuitController::class, 'index'])->name('index');
        Route::post('/', [CircuitController::class, 'store'])->name('store');
        Route::get('/{circuit}', [CircuitController::class, 'show'])->name('show');
        Route::put('/{circuit}', [CircuitController::class, 'update'])->name('update');
        Route::delete('/{circuit}', [CircuitController::class, 'destroy'])->name('destroy');
    });

    // Baskets
    Route::prefix('baskets')->name('baskets.')->group(function (): void {
        Route::post('/', [BasketController::class, 'store'])->name('store');
        Route::put('/{basket}', [BasketController::class, 'update'])->name('update');
        Route::delete('/{basket}', [BasketController::class, 'destroy'])->name('destroy');
    });

    // Messages
    Route::prefix('circuits/{circuit}/messages')->name('messages.')->group(function (): void {
        Route::post('/', [MessageController::class, 'store'])->name('store');
        Route::put('/{message}', [MessageController::class, 'update'])->name('update');
        Route::delete('/{message}', [MessageController::class, 'destroy'])->name('destroy');
    });
});
