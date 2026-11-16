<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class JobActionTest extends TestCase
{
    public function test_retry_action_is_denied_without_the_authorization_gate(): void
    {
        $this->post('/queue-monitor/unknown/jobs/123/retry', ['queue' => 'default'])
            ->assertForbidden();
    }

    public function test_delete_action_is_denied_without_the_authorization_gate(): void
    {
        $this->delete('/queue-monitor/unknown/jobs/123', ['queue' => 'default'])
            ->assertForbidden();
    }

    public function test_retry_action_is_denied_when_the_driver_does_not_support_it(): void
    {
        $this->app['gate']->define('viewQueueMonitor', static fn ($user): bool => true);

        $this->post('/queue-monitor/unknown/jobs/123/retry', ['queue' => 'default'])
            ->assertNotFound();
    }
}
