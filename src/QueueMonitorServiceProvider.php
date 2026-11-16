<?php

namespace MertAydin\LaravelQueueMonitor;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MertAydin\LaravelQueueMonitor\Alerts\QueueAlertDispatcher;
use MertAydin\LaravelQueueMonitor\Console\CheckQueueHealthCommand;
use MertAydin\LaravelQueueMonitor\Drivers\BeanstalkdQueueDriverFactory;
use MertAydin\LaravelQueueMonitor\Drivers\DatabaseQueueDriverFactory;
use MertAydin\LaravelQueueMonitor\Drivers\RedisQueueDriverFactory;
use MertAydin\LaravelQueueMonitor\Drivers\SqsQueueDriverFactory;
use MertAydin\LaravelQueueMonitor\Drivers\SyncQueueDriverFactory;
use MertAydin\LaravelQueueMonitor\Support\PayloadMasker;

class QueueMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/queue-monitor.php', 'queue-monitor');

        $this->app->singleton(QueueDriverManager::class);
        $this->app->singleton(BeanstalkdQueueDriverFactory::class);
        $this->app->singleton(DatabaseQueueDriverFactory::class);
        $this->app->singleton(RedisQueueDriverFactory::class);
        $this->app->singleton(SqsQueueDriverFactory::class);
        $this->app->singleton(SyncQueueDriverFactory::class);
        $this->app->singleton(PayloadMasker::class);
        $this->app->singleton(QueueLifecycleRecorder::class);
        $this->app->singleton(WorkerHeartbeatRecorder::class);
        $this->app->singleton(QueueAlertDispatcher::class);
    }

    private function registerConfiguredDrivers(): void
    {
        $beanstalkdFactory = $this->app->make(BeanstalkdQueueDriverFactory::class);
        $databaseFactory = $this->app->make(DatabaseQueueDriverFactory::class);
        $redisFactory = $this->app->make(RedisQueueDriverFactory::class);
        $sqsFactory = $this->app->make(SqsQueueDriverFactory::class);
        $syncFactory = $this->app->make(SyncQueueDriverFactory::class);
        $manager = $this->app->make(QueueDriverManager::class);

        foreach ((array) config('queue.connections', []) as $connectionName => $configuration) {
            if (($configuration['driver'] ?? null) === 'database') {
                $manager->register($connectionName, $databaseFactory->make($connectionName, $configuration));
            } elseif (($configuration['driver'] ?? null) === 'redis') {
                $manager->register($connectionName, $redisFactory->make($connectionName, $configuration));
            } elseif (($configuration['driver'] ?? null) === 'beanstalkd') {
                $manager->register($connectionName, $beanstalkdFactory->make($connectionName, $configuration));
            } elseif (($configuration['driver'] ?? null) === 'sqs') {
                $manager->register($connectionName, $sqsFactory->make($connectionName, $configuration));
            } elseif (($configuration['driver'] ?? null) === 'sync') {
                $manager->register($connectionName, $syncFactory->make($connectionName, $configuration));
            }
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CheckQueueHealthCommand::class]);
        }

        $this->publishes([
            __DIR__.'/../config/queue-monitor.php' => config_path('queue-monitor.php'),
        ], 'queue-monitor-config');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'queue-monitor');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->publishes([
            __DIR__.'/../resources/css/queue-monitor.css' => public_path('vendor/queue-monitor/queue-monitor.css'),
        ], 'queue-monitor-assets');
        $this->registerConfiguredDrivers();

        Event::listen(WorkerStarting::class, function (WorkerStarting $event): void {
            $this->app->make(WorkerHeartbeatRecorder::class)->starting($event);
        });

        Event::listen(Looping::class, function (Looping $event): void {
            $this->app->make(WorkerHeartbeatRecorder::class)->looping($event);
        });

        Event::listen(WorkerStopping::class, function (WorkerStopping $event): void {
            $this->app->make(WorkerHeartbeatRecorder::class)->stopping($event);
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $this->app->make(WorkerHeartbeatRecorder::class)->jobProcessing($event);
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            $this->app->make(QueueLifecycleRecorder::class)->processing($event);
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event): void {
            $this->app->make(QueueLifecycleRecorder::class)->processed($event);
        });

        Event::listen(JobFailed::class, function (JobFailed $event): void {
            $this->app->make(QueueLifecycleRecorder::class)->failed($event);
            $this->app->make(QueueAlertDispatcher::class)->jobFailed($event);
        });
    }
}
