<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use MertAydin\LaravelQueueMonitor\Contracts\QueueDriver;
use MertAydin\LaravelQueueMonitor\Data\QueueCapabilities;
use MertAydin\LaravelQueueMonitor\Data\QueueJob;
use MertAydin\LaravelQueueMonitor\Data\QueueSnapshot;
use MertAydin\LaravelQueueMonitor\Support\PayloadMasker;
use RuntimeException;

final class RedisQueueDriver implements QueueDriver
{
    public function __construct(
        private readonly string $connectionName,
        private readonly object $redis,
        private readonly array $queues = ['default'],
        private readonly string $queuePrefix = 'queues:',
        private readonly ?string $historyDatabase = null,
        private readonly string $historyTable = 'queue_monitor_jobs',
        private readonly ?PayloadMasker $payloadMasker = null,
    ) {
    }

    public function name(): string
    {
        return 'redis';
    }

    public function capabilities(): QueueCapabilities
    {
        $historyAvailable = $this->historyAvailable();

        return new QueueCapabilities(
            canListQueues: true,
            canInspectPending: true,
            canInspectRunning: true,
            canInspectCompleted: $historyAvailable,
            canInspectFailed: false,
            canRetry: true,
            canDelete: true,
            hasPersistentHistory: $historyAvailable,
        );
    }

    public function queues(): array
    {
        $queues = array_values(array_unique(array_filter(array_map('strval', $this->queues))));
        sort($queues);

        return $queues;
    }

    public function snapshot(?string $queue = null): QueueSnapshot
    {
        $queueNames = $queue === null ? $this->queues() : [$queue];
        $pending = $running = $delayed = $completed = 0;

        foreach ($queueNames as $queueName) {
            $pending += (int) $this->redis->command('llen', [$this->key($queueName)]);
            $running += (int) $this->redis->command('zcard', [$this->key($queueName).':reserved']);
            $delayed += (int) $this->redis->command('zcard', [$this->key($queueName).':delayed']);

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
                'driver' => 'redis',
                'delayed' => $delayed,
                'failed_inspection_supported' => false,
            ],
        );
    }

    public function jobs(string $queue, ?string $status = null, int $limit = 50): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The job limit must be greater than zero.');
        }

        $limit = min($limit, 500);
        $jobs = [];

        $structures = [
            'pending' => ['lrange', $this->key($queue)],
            'running' => ['zrange', $this->key($queue).':reserved'],
            'delayed' => ['zrange', $this->key($queue).':delayed'],
        ];

        foreach ($structures as $jobStatus => [$command, $key]) {
            if ($status !== null && $status !== $jobStatus) {
                continue;
            }

            $payloads = $this->redis->command($command, [$key, 0, $limit - 1]) ?? [];

            foreach ($payloads as $rawPayload) {
                $payload = json_decode((string) $rawPayload, true);

                if (! is_array($payload)) {
                    continue;
                }

                $jobs[] = $this->makeJob($queue, $payload, (string) $rawPayload, $jobStatus);
            }
        }

        if (($status === null || $status === 'completed') && $this->historyAvailable()) {
            $rows = $this->historyQuery()
                ->where('queue', $queue)
                ->where('status', 'completed')
                ->orderByDesc('finished_at')
                ->limit($limit)
                ->get();

            foreach ($rows as $row) {
                $payload = json_decode((string) $row->payload, true);

                $jobs[] = new QueueJob(
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
            }
        }

        return array_slice($jobs, 0, $limit);
    }

    public function retry(string $jobId, ?string $queue = null): void
    {
        foreach ($queue === null ? $this->queues() : [$queue] as $queueName) {
            foreach ([$this->key($queueName).':reserved', $this->key($queueName).':delayed'] as $sourceKey) {
                $payload = $this->findPayload($sourceKey, $jobId, true);

                if ($payload !== null && (int) $this->redis->command('zrem', [$sourceKey, $payload]) > 0) {
                    $this->redis->command('rpush', [$this->key($queueName), $payload]);

                    return;
                }
            }

            if ($this->findPayload($this->key($queueName), $jobId, false) !== null) {
                return;
            }
        }

        throw new RuntimeException(sprintf('Redis queue job [%s] was not found.', $jobId));
    }

    public function delete(string $jobId, ?string $queue = null): void
    {
        foreach ($queue === null ? $this->queues() : [$queue] as $queueName) {
            $pendingKey = $this->key($queueName);
            $payload = $this->findPayload($pendingKey, $jobId, false);

            if ($payload !== null) {
                $this->redis->command('lrem', [$pendingKey, 1, $payload]);

                continue;
            }

            foreach ([$pendingKey.':reserved', $pendingKey.':delayed'] as $sortedSetKey) {
                $payload = $this->findPayload($sortedSetKey, $jobId, true);

                if ($payload !== null) {
                    $this->redis->command('zrem', [$sortedSetKey, $payload]);

                    break;
                }
            }
        }
    }

    private function makeJob(string $queue, array $payload, string $rawPayload, string $status): QueueJob
    {
        return new QueueJob(
            id: (string) ($payload['uuid'] ?? sha1($rawPayload)),
            connection: $this->connectionName,
            queue: $queue,
            status: $status,
            name: $payload['displayName'] ?? $payload['job'] ?? null,
            payload: $this->maskPayload($payload),
            attempts: isset($payload['attempts']) ? (int) $payload['attempts'] : null,
            createdAt: isset($payload['createdAt']) ? (int) $payload['createdAt'] : null,
        );
    }

    private function findPayload(string $key, string $jobId, bool $sortedSet): ?string
    {
        $command = $sortedSet ? 'zrange' : 'lrange';

        foreach ($this->redis->command($command, [$key, 0, -1]) ?? [] as $rawPayload) {
            $rawPayload = (string) $rawPayload;
            $payload = json_decode($rawPayload, true);

            if (is_array($payload) && (string) ($payload['uuid'] ?? sha1($rawPayload)) === $jobId) {
                return $rawPayload;
            }
        }

        return null;
    }

    private function maskPayload(array $payload): array
    {
        if (! config('queue-monitor.payload.mask_sensitive_data', true)) {
            return $payload;
        }

        return ($this->payloadMasker ?? new PayloadMasker)->mask(
            $payload,
            (array) config('queue-monitor.payload.sensitive_keys', [])
        );
    }

    private function key(string $queue): string
    {
        return $this->queuePrefix.$queue;
    }

    private function historyAvailable(): bool
    {
        return (bool) config('queue-monitor.history.enabled', false)
            && Schema::connection($this->historyDatabase)->hasTable($this->historyTable);
    }

    private function historyQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::connection($this->historyDatabase)->table($this->historyTable)
            ->where('connection', $this->connectionName);
    }
}
