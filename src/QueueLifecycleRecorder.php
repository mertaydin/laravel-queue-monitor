<?php

namespace MertAydin\LaravelQueueMonitor;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use MertAydin\LaravelQueueMonitor\Support\PayloadMasker;
use Throwable;

final class QueueLifecycleRecorder
{
    public function __construct(private readonly PayloadMasker $payloadMasker) {}

    public function queued(object $event): void
    {
        if (! config('queue-monitor.history.enabled', false)) {
            return;
        }

        $payload = $event->payload ?? [];
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }

        if (! is_array($payload)) {
            $payload = [];
        }

        $jobId = $event->jobId ?? $payload['uuid'] ?? null;
        $jobId = $jobId === null ? null : (string) $jobId;
        if ($jobId === null || $jobId === '') {
            return;
        }

        if (config('queue-monitor.payload.mask_sensitive_data', true)) {
            $payload = $this->payloadMasker->mask(
                $payload,
                (array) config('queue-monitor.payload.sensitive_keys', [])
            );
        }

        $now = CarbonImmutable::now();
        $connectionName = (string) $event->connectionName;
        $workflow = $this->workflowMetadata($event->job ?? null);
        $jobData = $payload['data'] ?? [];
        $jobName = $payload['displayName'] ?? (is_array($jobData) ? ($jobData['commandName'] ?? null) : null);
        $record = [
            'queue' => (string) ($event->queue ?? 'default'),
            'job_name' => $jobName,
            'status' => 'pending',
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'attempts' => 0,
            'exception' => null,
            'queued_at' => $now,
            'started_at' => null,
            'finished_at' => null,
            'duration_ms' => null,
            'wait_duration_ms' => null,
            'batch_id' => $workflow['batch_id'],
            'chain_remaining' => $workflow['chain_remaining'],
            'updated_at' => $now,
        ];

        $query = $this->table()
            ->where('connection', $connectionName)
            ->where('job_id', $jobId);

        if ($query->exists()) {
            $query->update($record);

            return;
        }

        $this->table()->insert($record + [
            'connection' => $connectionName,
            'job_id' => $jobId,
            'created_at' => $now,
        ]);
    }

    public function processing(object $event): void
    {
        $this->store($event, 'running', true);
    }

    public function processed(object $event): void
    {
        $this->finish($event, 'completed');
    }

    public function failed(object $event): void
    {
        $this->finish($event, 'failed', $event->exception ?? null);
    }

    private function store(object $event, string $status, bool $resetStartTime = false, ?Throwable $exception = null): void
    {
        if (! config('queue-monitor.history.enabled', false)) {
            return;
        }

        $job = $event->job;
        $connectionName = (string) $event->connectionName;
        $jobId = $this->jobIdentifier($job);

        if ($jobId === null) {
            return;
        }

        $now = CarbonImmutable::now();
        $queuedAt = $resetStartTime
            ? $this->table()->where('connection', $connectionName)->where('job_id', $jobId)->value('queued_at')
            : null;
        $waitDuration = $queuedAt === null
            ? null
            : max(0, $now->getTimestampMs() - CarbonImmutable::parse($queuedAt)->getTimestampMs());
        $payload = $this->payload($job);
        $record = [
            'connection' => $connectionName,
            'job_id' => $jobId,
            'queue' => method_exists($job, 'getQueue') ? (string) $job->getQueue() : 'default',
            'job_name' => method_exists($job, 'resolveName') ? $job->resolveName() : null,
            'status' => $status,
            'payload' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'attempts' => method_exists($job, 'attempts') ? (int) $job->attempts() : 0,
            'exception' => $exception === null ? null : (string) $exception,
            'updated_at' => $now,
        ];

        if ($resetStartTime) {
            $record['started_at'] = $now;
            $record['finished_at'] = null;
            $record['duration_ms'] = null;
            $record['wait_duration_ms'] = $waitDuration;
        }

        $query = $this->table()
            ->where('connection', $connectionName)
            ->where('job_id', $jobId);

        if ($query->exists()) {
            $query->update($record);

            return;
        }

        $this->table()->insert($record + ['created_at' => $now]);
    }

    private function finish(object $event, string $status, ?Throwable $exception = null): void
    {
        if (! config('queue-monitor.history.enabled', false)) {
            return;
        }

        $job = $event->job;
        $connectionName = (string) $event->connectionName;
        $jobId = $this->jobIdentifier($job);

        if ($jobId === null) {
            return;
        }

        $now = CarbonImmutable::now();
        $existing = $this->table()
            ->where('connection', $connectionName)
            ->where('job_id', $jobId)
            ->first(['started_at']);

        $startedAt = $existing?->started_at;
        $duration = $startedAt === null
            ? null
            : max(0, $now->getTimestampMs() - CarbonImmutable::parse($startedAt)->getTimestampMs());

        $this->store($event, $status, false, $exception);

        $this->table()
            ->where('connection', $connectionName)
            ->where('job_id', $jobId)
            ->update([
                'status' => $status,
                'finished_at' => $now,
                'duration_ms' => $duration,
                'exception' => $exception === null ? null : (string) $exception,
                'updated_at' => $now,
            ]);
    }

    /** @return array{batch_id: string|null, chain_remaining: int} */
    private function workflowMetadata(mixed $job): array
    {
        if (! is_object($job)) {
            return ['batch_id' => null, 'chain_remaining' => 0];
        }

        $batchId = isset($job->batchId) ? trim((string) $job->batchId) : null;
        $chained = isset($job->chained) && is_array($job->chained) ? $job->chained : [];

        return [
            'batch_id' => $batchId === '' ? null : $batchId,
            'chain_remaining' => count($chained),
        ];
    }

    private function jobIdentifier(object $job): ?string
    {
        if (method_exists($job, 'getJobId') && $job->getJobId() !== null) {
            return (string) $job->getJobId();
        }

        if (method_exists($job, 'payload')) {
            $payload = $job->payload();

            if (isset($payload['uuid'])) {
                return (string) $payload['uuid'];
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function payload(object $job): ?array
    {
        if (! method_exists($job, 'payload')) {
            return null;
        }

        $payload = $job->payload();

        if (! is_array($payload)) {
            return null;
        }

        if (! config('queue-monitor.payload.mask_sensitive_data', true)) {
            return $payload;
        }

        return $this->payloadMasker->mask(
            $payload,
            (array) config('queue-monitor.payload.sensitive_keys', [])
        );
    }

    private function table(): Builder
    {
        $connection = config('queue-monitor.history.connection');

        return DB::connection($connection)->table(config('queue-monitor.history.table', 'queue_monitor_jobs'));
    }
}
