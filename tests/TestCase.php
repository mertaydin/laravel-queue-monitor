<?php

namespace MertAydin\LaravelQueueMonitor\Tests;

use Illuminate\Auth\AuthServiceProvider;
use Illuminate\Http\Request;
use MertAydin\LaravelQueueMonitor\Facades\QueueMonitor;
use MertAydin\LaravelQueueMonitor\QueueMonitorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function authorizeDashboard(bool $allowed): void
    {
        QueueMonitor::auth(static fn (Request $request): bool => $allowed);
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=');
    }

    protected function getPackageProviders($app): array
    {
        return [
            AuthServiceProvider::class,
            QueueMonitorServiceProvider::class,
        ];
    }
}
