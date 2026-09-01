<?php

use Illuminate\Support\Facades\Route;
use Maestrodimateo\Workflow\Controllers\BasketController;
use Maestrodimateo\Workflow\Controllers\CircuitController;
use Maestrodimateo\Workflow\Controllers\MessageController;

Route::name('workflow.')->group(function (): void {
    Route::apiResource('circuits', CircuitController::class);
    Route::apiResource('baskets', BasketController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('circuits.messages', MessageController::class)
        ->only(['store', 'update', 'destroy'])
        ->scoped();
});
