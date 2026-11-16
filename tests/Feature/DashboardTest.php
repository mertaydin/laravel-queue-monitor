<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_is_denied_when_the_authorization_gate_is_not_defined(): void
    {
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andThrow(new AuthorizationException);

        $this->get('/queue-monitor')->assertForbidden();
    }

    public function test_dashboard_renders_when_the_authorization_gate_allows_access(): void
    {
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andReturn(true);

        $this->get('/queue-monitor')
            ->assertOk()
            ->assertSee('Queue Monitor')
            ->assertSee('Queue overview');
    }
}
