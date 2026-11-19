<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use MertAydin\LaravelQueueMonitor\Contracts\QueueDriver;
use MertAydin\LaravelQueueMonitor\Data\QueueCapabilities;
use MertAydin\LaravelQueueMonitor\Data\QueueJob;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class JobActionTest extends TestCase
{
    public function test_retry_action_is_denied_without_the_authorization_gate(): void
    {
        $this->authorizeDashboard(false);

        $this->post('/queue-monitor/unknown/jobs/123/retry', ['queue' => 'default'])
            ->assertForbidden();
    }

    public function test_delete_action_is_denied_without_the_authorization_gate(): void
    {
        $this->authorizeDashboard(false);

        $this->delete('/queue-monitor/unknown/jobs/123', ['queue' => 'default'])
            ->assertForbidden();
    }

    public function test_retry_action_is_denied_when_the_driver_does_not_support_it(): void
    {
        $this->authorizeDashboard(true);

        $this->post('/queue-monitor/unknown/jobs/123/retry', ['queue' => 'default'])
            ->assertNotFound();
    }

    public function test_queue_specific_retry_limit_blocks_manual_retry(): void
    {
        $this->authorizeDashboard(true);

        config([
            'queue-monitor.retry.max_attempts' => 0,
            'queue-monitor.retry.max_attempts_by_queue' => ['emails' => 3],
        ]);

        $driver = \Mockery::mock(QueueDriver::class);
        $driver->shouldReceive('capabilities')->andReturn(new QueueCapabilities(canRetry: true));
        $driver->shouldReceive('queues')->andReturn(['emails']);
        $driver->shouldReceive('jobs')->with('emails', null, 500)->andReturn([
            new QueueJob('failed-1', 'fake', 'emails', 'failed', attempts: 4),
        ]);
        $driver->shouldNotReceive('retry');

        app(QueueDriverManager::class)->register('fake', $driver);

        $this->post('/queue-monitor/fake/jobs/failed-1/retry', ['queue' => 'emails'])
            ->assertStatus(422);
    }
}
