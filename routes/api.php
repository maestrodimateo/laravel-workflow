<?php

use Illuminate\Support\Facades\Route;
use Maestrodimateo\Workflow\Controllers\CircuitController;
use Maestrodimateo\Workflow\Controllers\WorkflowAdminController;

Route::name('workflow.')->group(function (): void {
    // Read-only public API
    Route::get('circuits', [CircuitController::class, 'index'])->name('circuits.index');
    Route::get('circuits/{circuit}', [CircuitController::class, 'show'])->name('circuits.show');

    // Configuration (consumed by SPA frontends)
    Route::get('config', [WorkflowAdminController::class, 'config'])->name('config');
    Route::get('circuits/{circuit}/baskets', [WorkflowAdminController::class, 'baskets'])->name('circuits.baskets');
    Route::get('circuits/{circuit}/messages', [WorkflowAdminController::class, 'messages'])->name('circuits.messages.index');
});