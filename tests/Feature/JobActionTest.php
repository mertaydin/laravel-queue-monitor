<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

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

    public function test_failed_job_detail_uses_the_failed_state_to_find_its_payload(): void
    {
        $this->authorizeDashboard(true);

        $driver = \\Mockery::mock(QueueDriver::class);
        $driver->shouldReceive('queues')->andReturn(['emails']);
        $driver->shouldReceive('name')->andReturn('fake');
        $driver->shouldReceive('capabilities')->andReturn(new QueueCapabilities(canInspectFailed: true));
        $driver->shouldReceive('jobs')->once()->with('emails', 'failed', 500)->andReturn([
            new QueueJob('failed-1', 'fake', 'emails', 'failed', payload: ['displayName' => 'PayloadVisibleJob']),
        ]);

        app(QueueDriverManager::class)->register('fake', $driver);

        $this->get('/queue-monitor/fake/jobs/failed-1?queue=emails&status=failed')
            ->assertOk()
            ->assertSee('PayloadVisibleJob');
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
