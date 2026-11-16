<?php

namespace MertAydin\LaravelQueueMonitor\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
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
