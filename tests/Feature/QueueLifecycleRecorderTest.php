<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use Illuminate\Support\Facades\DB;
use MertAydin\LaravelQueueMonitor\QueueLifecycleRecorder;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class QueueLifecycleRecorderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'queue-monitor.history.enabled' => true,
            'queue-monitor.history.table' => 'queue_monitor_jobs',
            'queue-monitor.payload.mask_sensitive_data' => true,
            'queue-monitor.payload.sensitive_keys' => ['token'],
        ]);

        DB::connection()->getSchemaBuilder()->create('queue_monitor_jobs', function ($table): void {
            $table->id();
            $table->string('connection');
            $table->string('job_id');
            $table->string('queue');
            $table->string('job_name')->nullable();
            $table->string('status');
            $table->timestamp('queued_at')->nullable();
            $table->longText('payload')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->longText('exception')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->unsignedBigInteger('wait_duration_ms')->nullable();
            $table->string('batch_id', 191)->nullable();
            $table->unsignedInteger('chain_remaining')->default(0);
            $table->timestamps();
            $table->unique(['connection', 'job_id']);
        });
    }

    protected function tearDown(): void
    {
        DB::connection()->getSchemaBuilder()->dropIfExists('queue_monitor_jobs');

        parent::tearDown();
    }

    public function test_it_records_batch_and_chain_metadata_from_the_queued_job(): void
    {
        $job = new class
        {
            public string $batchId = 'batch-123';

            public array $chained = ['serialized-next-job', 'serialized-final-job'];
        };

        $recorder = $this->app->make(QueueLifecycleRecorder::class);
        $recorder->queued((object) [
            'connectionName' => 'redis',
            'id' => 'job-workflow-123',
            'queue' => 'emails',
            'job' => $job,
            'payload' => ['uuid' => 'job-workflow-123', 'displayName' => 'App\\Jobs\\SendEmail'],
        ]);

        $record = DB::table('queue_monitor_jobs')->where('job_id', 'job-workflow-123')->first();

        $this->assertNotNull($record);
        $this->assertSame('batch-123', $record->batch_id);
        $this->assertSame(2, (int) $record->chain_remaining);
    }

    public function test_it_records_a_completed_job_and_masks_its_payload(): void
    {
        $job = new class
        {
            public function getJobId(): string
            {
                return 'job-123';
            }

            public function getQueue(): string
            {
                return 'emails';
            }

            public function resolveName(): string
            {
                return 'App\\Jobs\\SendEmail';
            }

            public function attempts(): int
            {
                return 1;
            }

            public function payload(): array
            {
                return ['data' => ['token' => 'private-value', 'recipient' => 'user@example.com']];
            }
        };

        $event = (object) ['connectionName' => 'redis', 'job' => $job];
        $recorder = $this->app->make(QueueLifecycleRecorder::class);

        $recorder->queued((object) [
            'connectionName' => 'redis',
            'id' => 'job-123',
            'queue' => 'emails',
            'payload' => ['uuid' => 'job-123', 'displayName' => 'App\\Jobs\\SendEmail'],
        ]);
        $recorder->processing($event);
        $createdAt = DB::table('queue_monitor_jobs')->where('job_id', 'job-123')->value('created_at');

        $recorder->processed($event);

        $record = DB::table('queue_monitor_jobs')->where('job_id', 'job-123')->first();

        $this->assertNotNull($record);
        $this->assertSame('completed', $record->status);
        $this->assertSame('redis', $record->connection);
        $this->assertSame('emails', $record->queue);
        $this->assertSame('[REDACTED]', json_decode($record->payload, true)['data']['token']);
        $this->assertSame('user@example.com', json_decode($record->payload, true)['data']['recipient']);
        $this->assertNotNull($record->finished_at);
        $this->assertNotNull($record->queued_at);
        $this->assertNotNull($record->wait_duration_ms);
        $this->assertSame($createdAt, $record->created_at);
    }
}
