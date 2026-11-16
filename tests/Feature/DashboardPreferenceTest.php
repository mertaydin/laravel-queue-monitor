<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class DashboardPreferenceTest extends TestCase
{
    public function test_dashboard_preferences_require_authorization(): void
    {
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andThrow(new AuthorizationException);

        $this->post('/queue-monitor/preferences', ['widgets' => ['analytics']])->assertForbidden();
    }

    public function test_dashboard_preferences_are_stored_in_the_session(): void
    {
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andReturn(true);

        $this->post('/queue-monitor/preferences', ['widgets' => ['analytics', 'workers'], 'refresh' => 30])
            ->assertRedirect('/queue-monitor')
            ->assertSessionHas('queue-monitor.dashboard.widgets', ['analytics', 'workers'])
            ->assertSessionHas('queue-monitor.dashboard.refresh', 30);
    }

    public function test_dashboard_preferences_reject_unknown_widgets(): void
    {
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andReturn(true);

        $this->from('/queue-monitor')->post('/queue-monitor/preferences', ['widgets' => ['unknown'], 'refresh' => 90])
            ->assertSessionHasErrors(['widgets.0', 'refresh']);
    }
}
