<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class QueueComparisonTest extends TestCase
{
    public function test_queue_comparison_requires_authorization(): void
    {
        Gate::shouldReceive('has')->once()->with('viewQueueMonitor')->andReturn(true);
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andThrow(new AuthorizationException);

        $this->get('/queue-monitor/compare')->assertForbidden();
    }

    public function test_queue_comparison_page_renders_for_authorized_users(): void
    {
        Gate::shouldReceive('has')->once()->with('viewQueueMonitor')->andReturn(true);
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andReturn(true);

        $this->get('/queue-monitor/compare')
            ->assertOk()
            ->assertSee('Queue comparison')
            ->assertSee('Queue workload');
    }
}
