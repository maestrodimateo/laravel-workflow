<?php

use Illuminate\Support\Facades\Route;
use Maestrodimateo\Workflow\Controllers\BasketController;
use Maestrodimateo\Workflow\Controllers\CircuitController;
use Maestrodimateo\Workflow\Controllers\MessageController;
use Maestrodimateo\Workflow\Controllers\WorkflowAdminController;

// Admin UI
Route::get('/', WorkflowAdminController::class)->name('workflow.admin');

// Admin API (shares web middleware for session-based auth)
Route::prefix('api')->name('workflow.admin.')->group(function (): void {

    Route::prefix('circuits')->name('circuits.')->group(function (): void {
        Route::get('/', [CircuitController::class, 'index'])->name('index');
        Route::post('/', [CircuitController::class, 'store'])->name('store');
        Route::get('/{circuit}', [CircuitController::class, 'show'])->name('show');
        Route::put('/{circuit}', [CircuitController::class, 'update'])->name('update');
        Route::delete('/{circuit}', [CircuitController::class, 'destroy'])->name('destroy');

        // Admin-specific: load all baskets for a circuit with relations (no pagination, no service)
        Route::get('/{circuit}/baskets', [WorkflowAdminController::class, 'baskets'])->name('baskets');
        Route::get('/{circuit}/messages', [WorkflowAdminController::class, 'messages'])->name('messages');
    });

    // Export / Import
    Route::get('/circuits/{circuit}/export', [WorkflowAdminController::class, 'export'])->name('circuits.export');
    Route::post('/circuits/import', [WorkflowAdminController::class, 'import'])->name('circuits.import');

    // Transitions
    Route::put('/transitions/{from}/{to}', [WorkflowAdminController::class, 'updateTransition'])->name('transitions.update');

    Route::prefix('baskets')->name('baskets.')->group(function (): void {
        Route::post('/', [BasketController::class, 'store'])->name('store');
        Route::put('/{basket}', [BasketController::class, 'update'])->name('update');
        Route::delete('/{basket}', [BasketController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('circuits/{circuit}/messages')->name('messages.')->group(function (): void {
        Route::post('/', [MessageController::class, 'store'])->name('store');
        Route::put('/{message}', [MessageController::class, 'update'])->name('update');
        Route::delete('/{message}', [MessageController::class, 'destroy'])->name('destroy');
    });
});
