<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class QueueComparisonTest extends TestCase
{
    public function test_queue_comparison_requires_authorization(): void
    {
        $this->authorizeDashboard(false);

        $this->get('/queue-monitor/compare')->assertForbidden();
    }

    public function test_queue_comparison_page_renders_for_authorized_users(): void
    {
        $this->authorizeDashboard(true);

        $this->get('/queue-monitor/compare')
            ->assertOk()
            ->assertSee('Queue comparison')
            ->assertSee('Queue workload');
    }
}
