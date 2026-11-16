<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Unit;

use MertAydin\LaravelQueueMonitor\Drivers\SqsQueueDriver;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class SqsQueueDriverTest extends TestCase
{
    public function test_sqs_metrics_are_available_without_claiming_payload_management(): void
    {
        $queue = new class
        {
            public function pendingSize(?string $queue = null): int
            {
                return 12;
            }

            public function reservedSize(?string $queue = null): int
            {
                return 3;
            }

            public function delayedSize(?string $queue = null): int
            {
                return 2;
            }
        };

        $driver = new SqsQueueDriver('sqs', $queue, ['emails', 'default']);
        $snapshot = $driver->snapshot('emails');
        $capabilities = $driver->capabilities();

        $this->assertSame(12, $snapshot->pending);
        $this->assertSame(3, $snapshot->running);
        $this->assertSame(2, $snapshot->metadata['delayed']);
        $this->assertFalse($capabilities->canInspectPending);
        $this->assertFalse($capabilities->canRetry);
        $this->assertFalse($capabilities->canDelete);
    }

    public function test_sqs_queue_names_are_deduplicated_and_sorted(): void
    {
        $driver = new SqsQueueDriver('sqs', new \stdClass, ['emails', 'default', 'emails']);

        $this->assertSame(['default', 'emails'], $driver->queues());
    }
}
