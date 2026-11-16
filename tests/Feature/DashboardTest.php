<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_is_denied_when_the_authorization_gate_is_not_defined(): void
    {
        $this->get('/queue-monitor')->assertForbidden();
    }

    public function test_dashboard_renders_when_the_authorization_gate_allows_access(): void
    {
        $this->app[\Illuminate\Contracts\Auth\Access\Gate::class]->define('viewQueueMonitor', static fn ($user): bool => true);

        $this->get('/queue-monitor')
            ->assertOk()
            ->assertSee('Queue Monitor')
            ->assertSee('Queue overview');
    }
}
