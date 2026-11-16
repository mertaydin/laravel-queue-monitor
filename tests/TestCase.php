<?php

namespace MertAydin\LaravelQueueMonitor\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [\MertAydin\LaravelQueueMonitor\QueueMonitorServiceProvider::class];
    }
}
