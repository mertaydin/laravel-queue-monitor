<?php

namespace MertAydin\LaravelQueueMonitor\Tests;

use Illuminate\Http\Request;
use MertAydin\LaravelQueueMonitor\Facades\QueueMonitor;
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
            \Illuminate\Auth\AuthServiceProvider::class,
            \MertAydin\LaravelQueueMonitor\QueueMonitorServiceProvider::class,
        ];
    }
}
