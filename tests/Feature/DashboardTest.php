<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_dashboard_is_accessible_when_the_authorization_gate_is_not_defined(): void
    {
        Gate::shouldReceive('has')->once()->with('viewQueueMonitor')->andReturn(false);

        $this->get('/queue-monitor')
            ->assertOk()
            ->assertSee('Queue Monitor');
    }

    public function test_dashboard_renders_when_the_authorization_gate_allows_access(): void
    {
        Gate::shouldReceive('has')->once()->with('viewQueueMonitor')->andReturn(true);
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andReturn(true);

        $this->get('/queue-monitor')
            ->assertOk()
            ->assertSee('Queue Monitor')
            ->assertSee('Queue overview');
    }

    public function test_dashboard_language_can_be_changed_without_javascript_url_construction(): void
    {
        Gate::shouldReceive('has')->once()->with('viewQueueMonitor')->andReturn(true);
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andReturn(true);

        $this->get('/queue-monitor?locale=tr&connection=sync')
            ->assertOk()
            ->assertSee('Kuyruk özeti')
            ->assertSee('name="locale"', false)
            ->assertSee('value="tr" selected', false)
            ->assertSee('name="connection" value="sync"', false)
            ->assertSee('method="GET"', false)
            ->assertDontSee('new URL(location.href)');
    }

    public function test_dashboard_summarizes_active_stale_and_stopped_workers(): void
    {
        Gate::shouldReceive('has')->once()->with('viewQueueMonitor')->andReturn(true);
        Gate::shouldReceive('authorize')->once()->with('viewQueueMonitor')->andReturn(true);

        config(['queue-monitor.workers.connection' => null, 'queue-monitor.workers.stale_after_seconds' => 90]);

        Schema::create('queue_monitor_workers', function (Blueprint $table): void {
            $table->id();
            $table->string('worker_key')->unique();
            $table->string('hostname');
            $table->unsignedBigInteger('process_id');
            $table->string('connection');
            $table->string('queue')->nullable();
            $table->string('status');
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_job_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });

        $now = CarbonImmutable::now();

        foreach ([
            ['active-worker', 'active-host', 101, 'active', $now],
            ['stale-worker', 'stale-host', 102, 'active', $now->subMinutes(5)],
            ['stopped-worker', 'stopped-host', 103, 'stopped', $now],
        ] as [$key, $host, $pid, $status, $heartbeat]) {
            DB::table('queue_monitor_workers')->insert([
                'worker_key' => $key,
                'hostname' => $host,
                'process_id' => $pid,
                'connection' => 'sync',
                'queue' => 'default',
                'status' => $status,
                'heartbeat_at' => $heartbeat,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->get('/queue-monitor')
            ->assertOk()
            ->assertSee('Worker capacity')
            ->assertSee('Active workers')
            ->assertSee('Stale workers')
            ->assertSee('Stopped workers')
            ->assertSee('Pending jobs / active worker')
            ->assertSee('active-host:101')
            ->assertSee('stale-host:102')
            ->assertSee('stopped-host:103');
    }
}
