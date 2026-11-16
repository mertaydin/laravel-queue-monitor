<?php

use Illuminate\Support\Facades\Route;
use MertAydin\LaravelQueueMonitor\Http\Controllers\DashboardController;

Route::middleware('web')
    ->prefix(trim((string) config('queue-monitor.path', 'queue-monitor'), '/'))
    ->name('queue-monitor.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('index');
    });
