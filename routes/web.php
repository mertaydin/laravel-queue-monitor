<?php

use Illuminate\Support\Facades\Route;
use MertAydin\LaravelQueueMonitor\Http\Middleware\ApplyDashboardTheme;
use MertAydin\LaravelQueueMonitor\Http\Controllers\DashboardController;
use MertAydin\LaravelQueueMonitor\Http\Controllers\DashboardPreferenceController;
use MertAydin\LaravelQueueMonitor\Http\Controllers\HealthCheckController;
use MertAydin\LaravelQueueMonitor\Http\Controllers\JobActionController;
use MertAydin\LaravelQueueMonitor\Http\Controllers\QueueComparisonController;
use MertAydin\LaravelQueueMonitor\Http\Controllers\JobDetailController;
use MertAydin\LaravelQueueMonitor\Http\Controllers\JobCompareController;
use MertAydin\LaravelQueueMonitor\Http\Controllers\SupervisorController;

Route::middleware(['web', ApplyDashboardTheme::class])
    ->prefix(trim((string) config('queue-monitor.path', 'queue-monitor'), '/'))
    ->name('queue-monitor.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('index');
        Route::get('/health', HealthCheckController::class)->name('health');
        Route::get('/compare', QueueComparisonController::class)->name('compare');
        Route::post('/preferences', DashboardPreferenceController::class)->name('preferences');
        Route::get('/{connection}/jobs/{jobId}', JobDetailController::class)->name('jobs.show');
        Route::post('/{connection}/jobs/{jobId}/retry', [JobActionController::class, 'retry'])->name('jobs.retry');
        Route::delete('/{connection}/jobs/{jobId}', [JobActionController::class, 'delete'])->name('jobs.delete');
        Route::post('/{connection}/jobs/bulk', [JobActionController::class, 'bulk'])->name('jobs.bulk');
        Route::post('/{connection}/jobs/compare', JobCompareController::class)->name('jobs.compare');
        Route::get('/supervisor/{server}', [SupervisorController::class, 'index'])->name('supervisor.index');
        Route::post('/supervisor/{server}/{process}/{action}', [SupervisorController::class, 'action'])
            ->whereIn('action', ['start', 'stop', 'restart'])
            ->name('supervisor.action');
    });
