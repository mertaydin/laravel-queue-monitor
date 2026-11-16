<?php

namespace MertAydin\LaravelQueueMonitor;

use Illuminate\Support\ServiceProvider;

class QueueMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/queue-monitor.php', 'queue-monitor');
        $this->app->singleton(QueueDriverManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/queue-monitor.php' => config_path('queue-monitor.php'),
        ], 'queue-monitor-config');
    }
}
