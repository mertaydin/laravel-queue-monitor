<?php

namespace MertAydin\LaravelQueueMonitor\Facades;

use Illuminate\Support\Facades\Facade;
use MertAydin\LaravelQueueMonitor\Services\DashboardAuthorizer;

/**
 * @method static void auth(callable $callback)
 *
 * @see DashboardAuthorizer
 */
class QueueMonitor extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return DashboardAuthorizer::class;
    }
}
