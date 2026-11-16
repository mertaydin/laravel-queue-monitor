<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Integration;

use MertAydin\LaravelQueueMonitor\Supervisor\SupervisorClient;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('supervisor-integration')]
class SupervisorServerIntegrationTest extends TestCase
{
    public function test_live_supervisor_server_can_list_stop_and_start_a_worker_process(): void
    {
        if (getenv('QUEUE_MONITOR_SUPERVISOR_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set QUEUE_MONITOR_SUPERVISOR_INTEGRATION=1 to run against a live Supervisor server.');
        }

        $client = new SupervisorClient([
            'url' => getenv('QUEUE_MONITOR_SUPERVISOR_URL') ?: 'http://127.0.0.1:9001/RPC2',
            'username' => getenv('QUEUE_MONITOR_SUPERVISOR_USER') ?: 'queue-monitor',
            'password' => getenv('QUEUE_MONITOR_SUPERVISOR_PASSWORD') ?: 'integration-test-secret',
            'allow_insecure_http' => true,
            'verify_tls' => true,
            'timeout' => 5,
        ]);

        $processes = $client->processes();
        $process = collect($processes)->firstWhere('name', 'queue-monitor-test');

        $this->assertIsArray($process);
        $this->assertSame('RUNNING', $process['statename']);

        $client->stop('queue-monitor-test');

        $stopped = collect($client->processes())->firstWhere('name', 'queue-monitor-test');
        $this->assertSame('STOPPED', $stopped['statename']);

        $client->start('queue-monitor-test');

        $started = collect($client->processes())->firstWhere('name', 'queue-monitor-test');
        $this->assertSame('RUNNING', $started['statename']);
    }
}
