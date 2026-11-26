<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use MertAydin\LaravelQueueMonitor\Contracts\QueueDriver;
use MertAydin\LaravelQueueMonitor\Data\QueueCapabilities;
use MertAydin\LaravelQueueMonitor\Data\QueueJob;
use MertAydin\LaravelQueueMonitor\Data\QueueSnapshot;
use MertAydin\LaravelQueueMonitor\Support\PayloadMasker;
use RuntimeException;

final class DatabaseQueueDriver implements QueueDriver
{
    public function __construct(
        private readonly string $connectionName,
        private readonly ConnectionInterface $database,
        private readonly string $jobsTable = 'jobs',
        private readonly ?string $failedDatabase = null,
        private readonly string $failedJobsTable = 'failed_jobs',
        private readonly ?string $historyDatabase = null,
        private readonly string $historyTable = 'queue_monitor_jobs',
        private readonly ?PayloadMasker $payloadMasker = null,
    ) {}

    public function name(): string
    {
        return 'database';
    }

    public function capabilities(): QueueCapabilities
    {
        return new QueueCapabilities(
            canListQueues: true,
            canInspectPending: true,
            canInspectRunning: true,
            canInspectCompleted: $this->historyEnabled() && $this->historyTableExists(),
            canInspectFailed: $this->failedTableExists(),
            canRetry: true,
            canDelete: true,
            hasPersistentHistory: $this->historyEnabled() && $this->historyTableExists(),
        );
    }

    public function queues(): array
    {
        $queues = $this->jobsQuery()->distinct()->pluck('queue')->all();

        if ($this->failedTableExists()) {
            $queues = array_merge($queues, $this->failedJobs()->distinct()->pluck('queue')->all());
        }

        if ($this->historyEnabled() && $this->historyTableExists()) {
            $queues = array_merge(
                $queues,
                $this->history()->where('connection', $this->connectionName)->distinct()->pluck('queue')->all()
            );
        }

        $queues = array_map('strval', $queues);
        $queues = array_values(array_unique($queues));
        sort($queues);

        return $queues;
    }

    public function snapshot(?string $queue = null): QueueSnapshot
    {
        $jobs = $this->jobsQuery();

        if ($queue !== null) {
            $jobs->where('queue', $queue);
        }

        $pending = (clone $jobs)->whereNull('reserved_at')->count();
        $running = (clone $jobs)->whereNotNull('reserved_at')->count();

        $completed = 0;
        if ($this->historyEnabled() && $this->historyTableExists()) {
            $completedQuery = $this->history()->where('connection', $this->connectionName)->where('status', 'completed');
            if ($queue !== null) {
                $completedQuery->where('queue', $queue);
            }
            $completed = $completedQuery->count();
        }

        $failed = 0;
        if ($this->failedTableExists()) {
            $failedQuery = $this->failedJobs();
            if ($queue !== null) {
                $failedQuery->where('queue', $queue);
            }
            $failed = $failedQuery->count();
        }

        return new QueueSnapshot(
            pending: $pending,
            running: $running,
            completed: $completed,
            failed: $failed,
            queue: $queue,
            metadata: ['driver' => $this->name()]
        );
    }

    public function jobs(string $queue, ?string $status = null, int $limit = 50): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The job limit must be greater than zero.');
        }

        $limit = min($limit, 500);
        $jobs = [];

        if ($status === null || $status === 'pending' || $status === 'running') {
            $query = $this->jobsQuery()->where('queue', $queue);

            if ($status === 'pending') {
                $query->whereNull('reserved_at');
            } elseif ($status === 'running') {
                $query->whereNotNull('reserved_at');
            }

            foreach ($query->orderByDesc('id')->limit($limit)->get() as $row) {
                $jobs[] = $this->databaseJob($row);
            }
        }

        if (($status === null || $status === 'failed') && $this->failedTableExists()) {
            foreach ($this->failedJobs()->where('queue', $queue)->orderByDesc('id')->limit($limit)->get() as $row) {
                $jobs[] = $this->failedJob($row);
            }
        }

        if (($status === null || $status === 'completed') && $this->historyEnabled() && $this->historyTableExists()) {
            foreach ($this->history()
                ->where('connection', $this->connectionName)
                ->where('queue', $queue)
                ->where('status', 'completed')
                ->orderByDesc('finished_at')
                ->limit($limit)
                ->get() as $row) {
                $jobs[] = $this->historyJob($row);
            }
        }

        usort($jobs, static function (QueueJob $first, QueueJob $second): int {
            $firstTimestamp = $first->completedAt ?? $first->reservedAt ?? $first->createdAt ?? 0;
            $secondTimestamp = $second->completedAt ?? $second->reservedAt ?? $second->createdAt ?? 0;

            return $secondTimestamp <=> $firstTimestamp;
        });

        return array_slice($jobs, 0, $limit);
    }

    public function retry(string $jobId, ?string $queue = null): void
    {
        if ($this->failedTableExists()) {
            $failedQuery = $this->failedJobs()->where('uuid', $jobId);
            if ($queue !== null) {
                $failedQuery->where('queue', $queue);
            }

            $failedJob = $failedQuery->first();

            if ($failedJob !== null) {
                $payload = json_decode($failedJob->payload, true);

                if (is_array($payload) === false) {
                    throw new RuntimeException('The failed job payload is not valid JSON.');
                }

                $now = time();
                $this->jobsQuery()->insert([
                    'queue' => $failedJob->queue,
                    'payload' => $failedJob->payload,
                    'attempts' => 0,
                    'reserved_at' => null,
                    'available_at' => $now,
                    'created_at' => $now,
                ]);

                $failedQuery->delete();

                return;
            }
        }

        $query = $this->jobsQuery()->where('id', $jobId);
        if ($queue !== null) {
            $query->where('queue', $queue);
        }

        if ($query->update(['reserved_at' => null, 'available_at' => time()]) === 0) {
            throw new RuntimeException(sprintf('Queue job [%s] was not found.', $jobId));
        }
    }

    public function delete(string $jobId, ?string $queue = null): void
    {
        // Laravel failed-job IDs are UUIDs, while the jobs table uses an integer primary key.
        // Comparing a UUID against an integer column can raise a MySQL conversion error.
        if (ctype_digit($jobId)) {
            $jobQuery = $this->jobsQuery()->where('id', $jobId);
            if ($queue !== null) {
                $jobQuery->where('queue', $queue);
            }
            $jobQuery->delete();
        }

        if ($this->failedTableExists()) {
            $failedQuery = $this->failedJobs()->where('uuid', $jobId);
            if ($queue !== null) {
                $failedQuery->where('queue', $queue);
            }
            $failedQuery->delete();
        }

        if ($this->historyEnabled() && $this->historyTableExists()) {
            $this->history()
                ->where('connection', $this->connectionName)
                ->where('job_id', $jobId)
                ->delete();
        }
    }

    private function jobsQuery(): Builder
    {
        return $this->database->table($this->jobsTable);
    }

    private function failedJobs(): Builder
    {
        return $this->failedConnection()->table($this->failedJobsTable)
            ->where('connection', $this->connectionName);
    }

    private function history(): Builder
    {
        return $this->historyConnection()->table($this->historyTable);
    }

    private function failedConnection(): ConnectionInterface
    {
        return app('db')->connection($this->failedDatabase);
    }

    private function historyConnection(): ConnectionInterface
    {
        return app('db')->connection($this->historyDatabase);
    }

    private function failedTableExists(): bool
    {
        return Schema::connection($this->failedDatabase)->hasTable($this->failedJobsTable);
    }

    private function historyEnabled(): bool
    {
        return (bool) config('queue-monitor.history.enabled', false);
    }

    private function historyTableExists(): bool
    {
        return Schema::connection($this->historyDatabase)->hasTable($this->historyTable);
    }

    private function databaseJob(object $row): QueueJob
    {
        $payload = $this->decodePayload((string) $row->payload);

        return new QueueJob(
            id: (string) $row->id,
            connection: $this->connectionName,
            queue: (string) $row->queue,
            status: $row->reserved_at === null ? 'pending' : 'running',
            name: $this->payloadName($payload),
            payload: is_array($payload) ? $payload : null,
            attempts: (int) $row->attempts,
            createdAt: (int) $row->created_at,
            reservedAt: $row->reserved_at === null ? null : (int) $row->reserved_at,
        );
    }

    private function failedJob(object $row): QueueJob
    {
        $payload = $this->decodePayload((string) $row->payload);

        return new QueueJob(
            id: (string) ($row->uuid ?? $row->id),
            connection: $this->connectionName,
            queue: (string) $row->queue,
            status: 'failed',
            name: $this->payloadName($payload),
            payload: is_array($payload) ? $payload : null,
            attempts: isset($payload['attempts']) ? (int) $payload['attempts'] : null,
            completedAt: isset($row->failed_at) ? strtotime((string) $row->failed_at) ?: null : null,
            exception: (string) $row->exception,
        );
    }

    private function historyJob(object $row): QueueJob
    {
        $payload = $this->decodePayload((string) $row->payload);

        return new QueueJob(
            id: (string) $row->job_id,
            connection: $this->connectionName,
            queue: (string) $row->queue,
            status: (string) $row->status,
            name: $row->job_name,
            payload: is_array($payload) ? $payload : null,
            attempts: (int) $row->attempts,
            createdAt: isset($row->created_at) ? strtotime((string) $row->created_at) ?: null : null,
            completedAt: isset($row->finished_at) ? strtotime((string) $row->finished_at) ?: null : null,
            durationMilliseconds: $row->duration_ms === null ? null : (int) $row->duration_ms,
            exception: $row->exception,
        );
    }

    /** @return array<string, mixed>|null */
    private function decodePayload(string $payload): ?array
    {
        $decodedPayload = json_decode($payload, true);

        if (is_array($decodedPayload) === false) {
            return null;
        }

        if (config('queue-monitor.payload.mask_sensitive_data', true) === false) {
            return $decodedPayload;
        }

        $masker = $this->payloadMasker ?? new PayloadMasker;

        return $masker->mask(
            $decodedPayload,
            (array) config('queue-monitor.payload.sensitive_keys', [])
        );
    }

    private function payloadName(mixed $payload): ?string
    {
        if (is_array($payload) === false) {
            return null;
        }

        return $payload['displayName'] ?? $payload['job'] ?? null;
    }
}
