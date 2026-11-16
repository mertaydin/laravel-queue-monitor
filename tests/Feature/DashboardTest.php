<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_is_denied_when_the_authorization_gate_is_not_defined(): void
    {
        $this->get('/queue-monitor')->assertForbidden();
    }

    public function test_dashboard_renders_when_the_authorization_gate_allows_access(): void
    {
        Gate::define('viewQueueMonitor', static fn (): bool => true);

        $this->get('/queue-monitor')
            ->assertOk()
            ->assertSee('Queue Monitor')
            ->assertSee('No queue connections registered');
    }
}
