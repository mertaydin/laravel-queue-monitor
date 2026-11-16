<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Unit;

use Illuminate\Support\Facades\Http;
use MertAydin\LaravelQueueMonitor\Supervisor\SupervisorClient;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;
use RuntimeException;

class SupervisorClientTest extends TestCase
{
    public function test_it_reads_supervisor_process_information(): void
    {
        Http::fake([
            '*' => Http::response('<?xml version="1.0"?><methodResponse><params><param><value><array><data><value><struct><member><name>name</name><value><string>queue-worker</string></value></member><member><name>pid</name><value><int>123</int></value></member><member><name>statename</name><value><string>RUNNING</string></value></member></struct></value></data></array></value></param></params></methodResponse>'),
        ]);

        $client = new SupervisorClient(['url' => 'https://supervisor.example.test/RPC2']);

        $processes = $client->processes();

        $this->assertSame('queue-worker', $processes[0]['name']);
        $this->assertSame(123, $processes[0]['pid']);
        $this->assertSame('RUNNING', $processes[0]['statename']);
    }

    public function test_it_rejects_plain_http_unless_explicitly_allowed(): void
    {
        $this->expectException(RuntimeException::class);

        (new SupervisorClient(['url' => 'http://supervisor.internal/RPC2']))->processes();
    }

    public function test_it_sends_start_commands_as_xml_rpc(): void
    {
        Http::fake([
            '*' => Http::response('<?xml version="1.0"?><methodResponse><params><param><value><boolean>1</boolean></value></param></params></methodResponse>'),
        ]);

        (new SupervisorClient(['url' => 'https://supervisor.example.test/RPC2']))->start('queue-worker');

        Http::assertSent(fn ($request): bool => str_contains($request->body(), 'supervisor.startProcess')
            && str_contains($request->body(), '<string>queue-worker</string>'));
    }
}
