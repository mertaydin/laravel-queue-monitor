<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Unit;

use MertAydin\LaravelQueueMonitor\Drivers\SyncQueueDriver;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class SyncQueueDriverTest extends TestCase
{
    public function test_sync_driver_reports_zero_queue_depth_and_disables_queue_actions(): void
    {
        $driver = new SyncQueueDriver('sync', ['default']);
        $snapshot = $driver->snapshot('default');
        $capabilities = $driver->capabilities();

        $this->assertSame(0, $snapshot->pending);
        $this->assertSame(0, $snapshot->running);
        $this->assertFalse($capabilities->canInspectPending);
        $this->assertFalse($capabilities->canRetry);
        $this->assertFalse($capabilities->canDelete);
    }

    public function test_sync_driver_returns_configured_queue_names(): void
    {
        $driver = new SyncQueueDriver('sync', ['default', 'emails']);

        $this->assertSame(['default', 'emails'], $driver->queues());
    }
}
