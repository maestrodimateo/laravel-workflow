<?php

use Illuminate\Support\Facades\Route;
use Maestrodimateo\Workflow\Controllers\BasketController;
use Maestrodimateo\Workflow\Controllers\CircuitController;
use Maestrodimateo\Workflow\Controllers\MessageController;
use Maestrodimateo\Workflow\Controllers\WorkflowAdminController;

Route::name('workflow.')->group(function (): void {
    Route::apiResource('circuits', CircuitController::class);
    Route::apiResource('baskets', BasketController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('circuits.messages', MessageController::class)
        ->only(['store', 'update', 'destroy'])
        ->scoped();

    // Configuration & designer endpoints (consumed by SPA frontends)
    Route::controller(WorkflowAdminController::class)->group(function (): void {
        Route::get('config', 'config')->name('config');
        Route::get('circuits/{circuit}/baskets', 'baskets')->name('circuits.baskets');
        Route::get('circuits/{circuit}/messages', 'messages')->name('circuits.messages.index');
        Route::patch('circuits/{circuit}/positions', 'positions')->name('circuits.positions');
        Route::get('circuits/{circuit}/export', 'export')->name('circuits.export');
        Route::post('circuits/import', 'import')->name('circuits.import');
        Route::put('transitions/{from}/{to}', 'updateTransition')->name('transitions.update');
    });
});
