<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Unit;

use MertAydin\LaravelQueueMonitor\Drivers\RedisQueueDriver;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class RedisQueueDriverTest extends TestCase
{
    public function test_it_reports_queue_depth_and_delayed_jobs(): void
    {
        $driver = new RedisQueueDriver('redis', new FakeRedisConnection(['llen' => 2, 'zcard' => 1]), ['emails']);
        $snapshot = $driver->snapshot('emails');

        $this->assertSame(2, $snapshot->pending);
        $this->assertSame(1, $snapshot->running);
        $this->assertSame(1, $snapshot->metadata['delayed']);
        $this->assertSame(0, $snapshot->failed);
    }

    public function test_it_reads_pending_jobs_and_masks_sensitive_payload_values(): void
    {
        $payload = json_encode([
            'uuid' => 'job-1',
            'displayName' => 'SendEmail',
            'data' => ['password' => 'secret'],
        ]);
        $redis = new FakeRedisConnection(['lrange' => [$payload]]);
        $jobs = (new RedisQueueDriver('redis', $redis, ['emails']))->jobs('emails', 'pending');

        $this->assertCount(1, $jobs);
        $this->assertSame('[REDACTED]', $jobs[0]->payload['data']['password']);
    }

    public function test_it_moves_reserved_jobs_back_to_pending(): void
    {
        $payload = json_encode(['uuid' => 'job-1']);
        $redis = new FakeRedisConnection(['zrange' => [$payload], 'zrem' => 1]);

        (new RedisQueueDriver('redis', $redis, ['emails']))->retry('job-1');

        $this->assertContains(['rpush', ['queues:emails', $payload]], $redis->commands);
    }

    public function test_it_deletes_pending_jobs(): void
    {
        $payload = json_encode(['uuid' => 'job-1']);
        $redis = new FakeRedisConnection(['lrange' => [$payload], 'lrem' => 1]);

        (new RedisQueueDriver('redis', $redis, ['emails']))->delete('job-1');

        $this->assertContains(['lrem', ['queues:emails', 1, $payload]], $redis->commands);
    }

    public function test_it_does_not_advertise_failed_job_inspection(): void
    {
        $driver = new RedisQueueDriver('redis', new FakeRedisConnection, ['default']);

        $this->assertFalse($driver->capabilities()->canInspectFailed);
    }
}

class FakeRedisConnection
{
    public array $commands = [];

    public function __construct(private readonly array $responses = [])
    {
    }

    public function command(string $command, array $parameters = []): mixed
    {
        $this->commands[] = [$command, $parameters];

        return $this->responses[$command] ?? 0;
    }
}
