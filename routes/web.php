<?php

use Illuminate\Support\Facades\Route;
use MertAydin\LaravelQueueMonitor\Http\Controllers\DashboardController;
use MertAydin\LaravelQueueMonitor\Http\Controllers\JobActionController;

Route::middleware('web')
    ->prefix(trim((string) config('queue-monitor.path', 'queue-monitor'), '/'))
    ->name('queue-monitor.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('index');
        Route::post('/{connection}/jobs/{jobId}/retry', [JobActionController::class, 'retry'])->name('jobs.retry');
        Route::delete('/{connection}/jobs/{jobId}', [JobActionController::class, 'delete'])->name('jobs.delete');
    });
