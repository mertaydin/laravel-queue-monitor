<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class SupervisorControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue-monitor.supervisor.servers.local' => [
                'enabled' => true,
                'url' => 'https://supervisor.example.test/RPC2',
                'programs' => ['worker-*'],
            ],
        ]);
    }

    public function test_process_controls_require_the_dedicated_management_gate(): void
    {
        Gate::shouldReceive('has')->once()->with('viewQueueMonitor')->andReturn(false);
        Gate::shouldReceive('authorize')
            ->once()
            ->with('manageQueueWorkers')
            ->andThrow(new AuthorizationException);

        $this->post('/queue-monitor/supervisor/local/worker-00/start')
            ->assertForbidden();
    }

    public function test_only_allowlisted_supervisor_processes_can_be_controlled(): void
    {
        Gate::shouldReceive('has')->once()->with('viewQueueMonitor')->andReturn(false);
        Gate::shouldReceive('authorize')
            ->once()
            ->with('manageQueueWorkers')
            ->andReturn(true);

        $this->post('/queue-monitor/supervisor/local/not-allowed/start')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_authorized_process_can_be_started(): void
    {
        Gate::shouldReceive('has')->once()->with('viewQueueMonitor')->andReturn(false);
        Gate::shouldReceive('authorize')
            ->once()
            ->with('manageQueueWorkers')
            ->andReturn(true);

        Http::fake([
            '*' => Http::response('<?xml version="1.0"?><methodResponse><params><param><value><boolean>1</boolean></value></param></params></methodResponse>'),
        ]);

        $this->post('/queue-monitor/supervisor/local/worker-00/start')
            ->assertRedirect('/queue-monitor/supervisor/local')
            ->assertSessionHas('status');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://supervisor.example.test/RPC2' && $request->method() === 'POST');
    }
}
