<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Unit;

use InvalidArgumentException;
use MertAydin\LaravelQueueMonitor\Contracts\QueueDriver;
use MertAydin\LaravelQueueMonitor\Data\QueueCapabilities;
use MertAydin\LaravelQueueMonitor\Data\QueueSnapshot;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class QueueDriverManagerTest extends TestCase
{
    public function test_it_registers_and_resolves_a_driver(): void
    {
        $manager = new QueueDriverManager;
        $driver = new class implements QueueDriver
        {
            public function name(): string
            {
                return 'fake';
            }

            public function capabilities(): QueueCapabilities
            {
                return new QueueCapabilities;
            }

            public function queues(): array
            {
                return [];
            }

            public function snapshot(?string $queue = null): QueueSnapshot
            {
                return new QueueSnapshot;
            }

            public function jobs(string $queue, ?string $status = null, int $limit = 50): array
            {
                return [];
            }

            public function retry(string $jobId, ?string $queue = null): void {}

            public function delete(string $jobId, ?string $queue = null): void {}
        };

        $manager->register('redis', $driver);

        $this->assertSame($driver, $manager->driver('redis'));
        $this->assertTrue($manager->has('redis'));
        $this->assertSame(['redis' => $driver], $manager->all());
    }

    public function test_it_throws_for_an_unknown_connection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new QueueDriverManager)->driver('missing');
    }
}
