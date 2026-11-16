<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class JobActionTest extends TestCase
{
    public function test_retry_action_is_denied_without_the_authorization_gate(): void
    {
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andThrow(new AuthorizationException);

        $this->post('/queue-monitor/unknown/jobs/123/retry', ['queue' => 'default'])
            ->assertForbidden();
    }

    public function test_delete_action_is_denied_without_the_authorization_gate(): void
    {
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andThrow(new AuthorizationException);

        $this->delete('/queue-monitor/unknown/jobs/123', ['queue' => 'default'])
            ->assertForbidden();
    }

    public function test_retry_action_is_denied_when_the_driver_does_not_support_it(): void
    {
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andReturn(true);

        $this->post('/queue-monitor/unknown/jobs/123/retry', ['queue' => 'default'])
            ->assertNotFound();
    }
}
