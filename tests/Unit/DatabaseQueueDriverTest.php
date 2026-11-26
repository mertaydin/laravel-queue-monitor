<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Unit;

use Illuminate\Support\Facades\DB;
use MertAydin\LaravelQueueMonitor\Drivers\DatabaseQueueDriver;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class DatabaseQueueDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'testing',
            'database.connections.testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'queue-monitor.history.enabled' => true,
            'queue-monitor.history.connection' => 'testing',
            'queue-monitor.history.table' => 'queue_monitor_jobs',
        ]);

        DB::purge('testing');
        $schema = DB::connection('testing')->getSchemaBuilder();

        $schema->create('jobs', function ($table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        $schema->create('failed_jobs', function ($table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        $schema->create('queue_monitor_jobs', function ($table): void {
            $table->id();
            $table->string('connection');
            $table->string('job_id');
            $table->string('queue');
            $table->string('job_name')->nullable();
            $table->string('status');
            $table->longText('payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->longText('exception')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestamps();
        });

        DB::table('jobs')->insert([
            ['queue' => 'emails', 'payload' => json_encode(['displayName' => 'SendEmail']), 'attempts' => 0, 'reserved_at' => null, 'available_at' => time(), 'created_at' => time()],
            ['queue' => 'emails', 'payload' => json_encode(['displayName' => 'SendInvoice']), 'attempts' => 1, 'reserved_at' => time(), 'available_at' => time(), 'created_at' => time()],
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => 'failed-uuid',
            'connection' => 'database',
            'queue' => 'emails',
            'payload' => json_encode(['displayName' => 'SendReceipt']),
            'exception' => 'RuntimeException: failed',
            'failed_at' => now(),
        ]);

        DB::table('queue_monitor_jobs')->insert([
            'connection' => 'database',
            'job_id' => 'completed-uuid',
            'queue' => 'emails',
            'job_name' => 'SendWelcome',
            'status' => 'completed',
            'payload' => json_encode(['displayName' => 'SendWelcome']),
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'finished_at' => now(),
            'duration_ms' => 120,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('testing');

        parent::tearDown();
    }

    private function driver(): DatabaseQueueDriver
    {
        return new DatabaseQueueDriver(
            connectionName: 'database',
            database: DB::connection('testing'),
            historyDatabase: 'testing',
        );
    }

    public function test_it_lists_queues_and_reports_queue_counts(): void
    {
        $driver = $this->driver();

        $this->assertSame(['emails'], $driver->queues());

        $snapshot = $driver->snapshot('emails');

        $this->assertSame(1, $snapshot->pending);
        $this->assertSame(1, $snapshot->running);
        $this->assertSame(1, $snapshot->completed);
        $this->assertSame(1, $snapshot->failed);
    }

    public function test_it_lists_pending_running_failed_and_completed_jobs(): void
    {
        $driver = $this->driver();

        $this->assertSame('pending', $driver->jobs('emails', 'pending')[0]->status);
        $this->assertSame('running', $driver->jobs('emails', 'running')[0]->status);
        $this->assertSame('failed', $driver->jobs('emails', 'failed')[0]->status);
        $this->assertSame('completed', $driver->jobs('emails', 'completed')[0]->status);
    }

    public function test_it_retries_a_failed_job_by_returning_it_to_the_jobs_table(): void
    {
        $driver = $this->driver();

        $driver->retry('failed-uuid', 'emails');

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(3, DB::table('jobs')->count());
        $this->assertSame(0, (int) DB::table('jobs')->orderByDesc('id')->value('attempts'));
    }

    public function test_it_deletes_jobs_from_the_queue_and_history(): void
    {
        $driver = $this->driver();

        $driver->delete('1', 'emails');
        $driver->delete('completed-uuid');

        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('queue_monitor_jobs')->count());
    }

    public function test_it_deletes_a_failed_job_by_uuid_without_querying_the_integer_jobs_table(): void
    {
        $driver = $this->driver();

        $driver->delete('failed-uuid', 'emails');

        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(2, DB::table('jobs')->count());
    }

    public function test_it_rejects_a_zero_job_limit(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->driver()->jobs('emails', limit: 0);
    }
}
