<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use MertAydin\LaravelQueueMonitor\Contracts\QueueDriver;
use MertAydin\LaravelQueueMonitor\Data\QueueCapabilities;
use MertAydin\LaravelQueueMonitor\Data\QueueJob;
use MertAydin\LaravelQueueMonitor\Data\QueueSnapshot;
use MertAydin\LaravelQueueMonitor\Support\PayloadMasker;
use RuntimeException;

final class SyncQueueDriver implements QueueDriver
{
    public function __construct(
        private readonly string $connectionName,
        private readonly array $configuredQueues = ['default'],
        private readonly ?string $historyDatabase = null,
        private readonly string $historyTable = 'queue_monitor_jobs',
        private readonly ?PayloadMasker $payloadMasker = null,
    ) {}

    public function name(): string
    {
        return 'sync';
    }

    public function capabilities(): QueueCapabilities
    {
        $historyAvailable = $this->historyAvailable();

        return new QueueCapabilities(
            canListQueues: true,
            canInspectPending: false,
            canInspectRunning: false,
            canInspectCompleted: $historyAvailable,
            canInspectFailed: $historyAvailable,
            canRetry: false,
            canDelete: false,
            hasPersistentHistory: $historyAvailable,
        );
    }

    public function queues(): array
    {
        $queues = array_values(array_unique(array_filter(array_map('strval', $this->configuredQueues))));
        sort($queues);

        return $queues;
    }

    public function snapshot(?string $queue = null): QueueSnapshot
    {
        $completed = $failed = 0;
        $queues = $queue === null ? $this->queues() : [$queue];

        if ($this->historyAvailable()) {
            foreach ($queues as $queueName) {
                $completed += $this->historyQuery()->where('queue', $queueName)->where('status', 'completed')->count();
                $failed += $this->historyQuery()->where('queue', $queueName)->where('status', 'failed')->count();
            }
        }

        return new QueueSnapshot(
            pending: 0,
            running: 0,
            completed: $completed,
            failed: $failed,
            queue: $queue,
            metadata: [
                'driver' => $this->name(),
                'queue_depth_supported' => false,
                'history_available' => $this->historyAvailable(),
            ],
        );
    }

    public function jobs(string $queue, ?string $status = null, int $limit = 50): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The job limit must be greater than zero.');
        }

        if (! $this->historyAvailable()) {
            return [];
        }

        $query = $this->historyQuery()->where('queue', $queue);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->orderByDesc('finished_at')
            ->limit(min($limit, 500))
            ->get()
            ->map(function ($row): QueueJob {
                $payload = json_decode((string) $row->payload, true);

                return new QueueJob(
                    id: (string) $row->job_id,
                    connection: $this->connectionName,
                    queue: (string) $row->queue,
                    status: (string) $row->status,
                    name: $row->job_name,
                    payload: is_array($payload) ? $this->maskPayload($payload) : null,
                    attempts: (int) $row->attempts,
                    createdAt: isset($row->created_at) ? strtotime((string) $row->created_at) ?: null : null,
                    completedAt: isset($row->finished_at) ? strtotime((string) $row->finished_at) ?: null : null,
                    durationMilliseconds: $row->duration_ms === null ? null : (int) $row->duration_ms,
                    exception: $row->exception,
                );
            })
            ->all();
    }

    public function retry(string $jobId, ?string $queue = null): void
    {
        throw new RuntimeException('Synchronous jobs cannot be retried from the queue dashboard.');
    }

    public function delete(string $jobId, ?string $queue = null): void
    {
        throw new RuntimeException('Synchronous jobs do not remain in a queue to be deleted.');
    }

    private function historyAvailable(): bool
    {
        return (bool) config('queue-monitor.history.enabled', false)
            && Schema::connection($this->historyDatabase)->hasTable($this->historyTable);
    }

    private function historyQuery(): Builder
    {
        return DB::connection($this->historyDatabase)
            ->table($this->historyTable)
            ->where('connection', $this->connectionName);
    }

    private function maskPayload(array $payload): array
    {
        if (! config('queue-monitor.payload.mask_sensitive_data', true)) {
            return $payload;
        }

        $masker = $this->payloadMasker ?? new PayloadMasker;

        return $masker->mask($payload, (array) config('queue-monitor.payload.sensitive_keys', []));
    }
}
