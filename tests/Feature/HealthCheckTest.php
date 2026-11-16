<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_page_requires_the_dashboard_authorization_gate(): void
    {
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andThrow(new AuthorizationException);

        $this->get('/queue-monitor/health')->assertForbidden();
    }

    public function test_health_page_renders_diagnostics_for_authorized_users(): void
    {
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andReturn(true);

        $this->get('/queue-monitor/health')
            ->assertOk()
            ->assertSee('System health')
            ->assertSee('Application database')
            ->assertSee('Worker heartbeat storage');
    }
}
