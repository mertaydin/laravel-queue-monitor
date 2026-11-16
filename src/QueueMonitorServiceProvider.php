<?php

namespace MertAydin\LaravelQueueMonitor;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MertAydin\LaravelQueueMonitor\Support\PayloadMasker;

class QueueMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/queue-monitor.php', 'queue-monitor');

        $this->app->singleton(QueueDriverManager::class);
        $this->app->singleton(PayloadMasker::class);
        $this->app->singleton(QueueLifecycleRecorder::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/queue-monitor.php' => config_path('queue-monitor.php'),
        ], 'queue-monitor-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $this->app->make(QueueLifecycleRecorder::class)->processing($event);
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event): void {
            $this->app->make(QueueLifecycleRecorder::class)->processed($event);
        });

        Event::listen(JobFailed::class, function (JobFailed $event): void {
            $this->app->make(QueueLifecycleRecorder::class)->failed($event);
        });
    }
}
