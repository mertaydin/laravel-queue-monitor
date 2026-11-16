<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Unit;

use MertAydin\LaravelQueueMonitor\Drivers\BeanstalkdQueueDriver;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class BeanstalkdQueueDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (class_exists('Pheanstalk\\Values\\TubeName', false) === false) {
            class_alias(FakeTubeName::class, 'Pheanstalk\\Values\\TubeName');
        }

        if (class_exists('Pheanstalk\\Values\\JobId', false) === false) {
            class_alias(FakeJobId::class, 'Pheanstalk\\Values\\JobId');
        }
    }

    public function test_it_lists_tubes_and_reports_ready_reserved_delayed_and_buried_counts(): void
    {
        $client = new FakeBeanstalkdClient;
        $driver = new BeanstalkdQueueDriver('beanstalkd', $client, ['default']);

        $this->assertSame(['default', 'emails'], $driver->queues());
        $snapshot = $driver->snapshot('emails');

        $this->assertSame(4, $snapshot->pending);
        $this->assertSame(2, $snapshot->running);
        $this->assertSame(3, $snapshot->failed);
        $this->assertSame(1, $snapshot->metadata['delayed']);
        $this->assertFalse($driver->capabilities()->canInspectRunning);
    }

    public function test_it_inspects_ready_delayed_and_buried_jobs_with_masking(): void
    {
        $client = new FakeBeanstalkdClient;
        $driver = new BeanstalkdQueueDriver('beanstalkd', $client, ['emails']);

        $jobs = $driver->jobs('emails');

        $this->assertCount(3, $jobs);
        $this->assertSame('pending', $jobs[0]->status);
        $this->assertSame('delayed', $jobs[1]->status);
        $this->assertSame('failed', $jobs[2]->status);
        $this->assertSame('[REDACTED]', $jobs[0]->payload['data']['password']);
    }

    public function test_it_kicks_a_job_when_retrying_and_deletes_by_job_id(): void
    {
        $client = new FakeBeanstalkdClient;
        $driver = new BeanstalkdQueueDriver('beanstalkd', $client, ['emails']);

        $driver->retry('42', 'emails');
        $driver->delete('42', 'emails');

        $this->assertSame('42', $client->kickedJobId);
        $this->assertSame('42', $client->deletedJobId);
    }
}

class FakeTubeName
{
    public function __construct(public readonly string $value)
    {
    }
}

class FakeJobId
{
    public function __construct(private readonly string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->id;
    }
}

class FakeBeanstalkdJob
{
    public function __construct(private readonly string $id, private readonly string $data)
    {
    }

    public function getId(): FakeJobId
    {
        return new FakeJobId($this->id);
    }

    public function getData(): string
    {
        return $this->data;
    }
}

class FakeBeanstalkdClient
{
    public ?string $kickedJobId = null;

    public ?string $deletedJobId = null;

    public function listTubes(): array
    {
        return [new FakeTubeName('emails'), new FakeTubeName('default')];
    }

    public function statsTube(object $tube): object
    {
        return (object) [
            'currentJobsReady' => 4,
            'currentJobsReserved' => 2,
            'currentJobsDelayed' => 1,
            'currentJobsBuried' => 3,
        ];
    }

    public function useTube(object $tube): void {}

    public function peekReady(): FakeBeanstalkdJob
    {
        return new FakeBeanstalkdJob('42', json_encode([
            'displayName' => 'SendEmail',
            'data' => ['password' => 'secret'],
        ]));
    }

    public function peekDelayed(): FakeBeanstalkdJob
    {
        return new FakeBeanstalkdJob('43', json_encode(['displayName' => 'DelayedEmail']));
    }

    public function peekBuried(): FakeBeanstalkdJob
    {
        return new FakeBeanstalkdJob('44', json_encode(['displayName' => 'FailedEmail']));
    }

    public function peek(object $id): FakeBeanstalkdJob
    {
        return new FakeBeanstalkdJob($id->getId(), json_encode(['displayName' => 'SendEmail']));
    }

    public function kickJob(object $id): void
    {
        $this->kickedJobId = $id->getId();
    }

    public function delete(FakeBeanstalkdJob $job): void
    {
        $this->deletedJobId = $job->getId()->getId();
    }
}
