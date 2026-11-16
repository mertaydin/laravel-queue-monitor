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

final class SqsQueueDriver implements QueueDriver
{
    public function __construct(
        private readonly string $connectionName,
        private readonly object $queueConnection,
        private readonly array $configuredQueues,
        private readonly ?string $historyDatabase = null,
        private readonly string $historyTable = 'queue_monitor_jobs',
        private readonly ?PayloadMasker $payloadMasker = null,
    ) {}

    public function name(): string
    {
        return 'sqs';
    }

    public function capabilities(): QueueCapabilities
    {
        $historyAvailable = $this->historyAvailable();

        return new QueueCapabilities(
            canListQueues: true,
            canInspectPending: false,
            canInspectRunning: false,
            canInspectCompleted: $historyAvailable,
            canInspectFailed: false,
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
        $pending = $running = $delayed = $completed = 0;
        $queues = $queue === null ? $this->queues() : [$queue];

        foreach ($queues as $queueName) {
            $pending += (int) $this->queueConnection->pendingSize($queueName);
            $running += (int) $this->queueConnection->reservedSize($queueName);
            $delayed += (int) $this->queueConnection->delayedSize($queueName);

            if ($this->historyAvailable()) {
                $completed += $this->historyQuery()
                    ->where('queue', $queueName)
                    ->where('status', 'completed')
                    ->count();
            }
        }

        return new QueueSnapshot(
            pending: $pending,
            running: $running,
            completed: $completed,
            failed: 0,
            queue: $queue,
            metadata: [
                'driver' => $this->name(),
                'delayed' => $delayed,
                'payload_inspection_supported' => false,
                'management_supported' => false,
            ],
        );
    }

    public function jobs(string $queue, ?string $status = null, int $limit = 50): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The job limit must be greater than zero.');
        }

        if ($status !== null && $status !== 'completed') {
            return [];
        }

        if (! $this->historyAvailable()) {
            return [];
        }

        return $this->historyQuery()
            ->where('queue', $queue)
            ->where('status', 'completed')
            ->orderByDesc('finished_at')
            ->limit(min($limit, 500))
            ->get()
            ->map(function ($row): QueueJob {
                $payload = json_decode((string) $row->payload, true);

                return new QueueJob(
                    id: (string) $row->job_id,
                    connection: $this->connectionName,
                    queue: (string) $row->queue,
                    status: 'completed',
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
        throw new \RuntimeException('SQS job retry is not supported without a live receipt handle.');
    }

    public function delete(string $jobId, ?string $queue = null): void
    {
        throw new \RuntimeException('SQS job deletion is not supported without a live receipt handle.');
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
