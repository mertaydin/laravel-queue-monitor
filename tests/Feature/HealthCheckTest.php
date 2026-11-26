<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_page_requires_the_dashboard_authorization_gate(): void
    {
        $this->authorizeDashboard(false);

        $this->get('/queue-monitor/health')->assertForbidden();
    }

    public function test_health_page_renders_diagnostics_for_authorized_users(): void
    {
        $this->authorizeDashboard(true);

        $this->get('/queue-monitor/health')
            ->assertOk()
            ->assertSee('System health')
            ->assertSee('Application database')
            ->assertSee('Worker heartbeat storage')
            ->assertSee('vendor/queue-monitor/queue-monitor.js')
            ->assertSee('id="queue-monitor-theme-picker"', false)
            ->assertSee('<option value="light">Light</option>', false)
            ->assertSee('<option value="dark">Dark</option>', false)
            ->assertSee('<option value="system">System</option>', false);
    }
}
