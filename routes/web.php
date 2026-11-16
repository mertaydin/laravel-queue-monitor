<?php

use Illuminate\Support\Facades\Route;
use MertAydin\LaravelQueueMonitor\Http\Controllers\DashboardController;
use MertAydin\LaravelQueueMonitor\Http\Controllers\JobActionController;
use MertAydin\LaravelQueueMonitor\Http\Controllers\JobDetailController;

Route::middleware('web')
    ->prefix(trim((string) config('queue-monitor.path', 'queue-monitor'), '/'))
    ->name('queue-monitor.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('index');
        Route::get('/{connection}/jobs/{jobId}', JobDetailController::class)->name('jobs.show');
        Route::post('/{connection}/jobs/{jobId}/retry', [JobActionController::class, 'retry'])->name('jobs.retry');
        Route::delete('/{connection}/jobs/{jobId}', [JobActionController::class, 'delete'])->name('jobs.delete');
        Route::post('/{connection}/jobs/bulk', [JobActionController::class, 'bulk'])->name('jobs.bulk');
    });
