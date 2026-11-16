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

final class BeanstalkdQueueDriver implements QueueDriver
{
    public function __construct(
        private readonly string $connectionName,
        private readonly object $pheanstalk,
        private readonly array $configuredQueues = ['default'],
        private readonly ?string $historyDatabase = null,
        private readonly string $historyTable = 'queue_monitor_jobs',
        private readonly ?PayloadMasker $payloadMasker = null,
    ) {}

    public function name(): string
    {
        return 'beanstalkd';
    }

    public function capabilities(): QueueCapabilities
    {
        $historyAvailable = $this->historyAvailable();

        return new QueueCapabilities(
            canListQueues: true,
            canInspectPending: true,
            canInspectRunning: false,
            canInspectCompleted: $historyAvailable,
            canInspectFailed: true,
            canRetry: true,
            canDelete: true,
            hasPersistentHistory: $historyAvailable,
        );
    }

    public function queues(): array
    {
        $tubes = [];

        foreach ($this->pheanstalk->listTubes() as $tube) {
            $tubes[] = (string) ($tube->value ?? $tube);
        }

        if ($tubes === []) {
            $tubes = $this->configuredQueues;
        }

        $tubes = array_values(array_unique(array_filter(array_map('strval', $tubes))));
        sort($tubes);

        return $tubes;
    }

    public function snapshot(?string $queue = null): QueueSnapshot
    {
        $pending = $running = $delayed = $buried = $completed = 0;
        $tubes = $queue === null ? $this->queues() : [$queue];

        foreach ($tubes as $tubeName) {
            $stats = $this->pheanstalk->statsTube($this->tube($tubeName));
            $pending += (int) $stats->currentJobsReady;
            $running += (int) $stats->currentJobsReserved;
            $delayed += (int) $stats->currentJobsDelayed;
            $buried += (int) $stats->currentJobsBuried;

            if ($this->historyAvailable()) {
                $completed += $this->historyQuery()
                    ->where('queue', $tubeName)
                    ->where('status', 'completed')
                    ->count();
            }
        }

        return new QueueSnapshot(
            pending: $pending,
            running: $running,
            completed: $completed,
            failed: $buried,
            queue: $queue,
            metadata: [
                'driver' => $this->name(),
                'delayed' => $delayed,
                'buried' => $buried,
                'running_inspection_supported' => false,
                'job_listing_limit_per_state' => 1,
            ],
        );
    }

    public function jobs(string $queue, ?string $status = null, int $limit = 50): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('The job limit must be greater than zero.');
        }

        $jobs = [];
        $states = [
            'pending' => 'peekReady',
            'delayed' => 'peekDelayed',
            'failed' => 'peekBuried',
        ];

        foreach ($states as $jobStatus => $method) {
            if ($status !== null && $status !== $jobStatus) {
                continue;
            }

            $this->pheanstalk->useTube($this->tube($queue));
            $job = $this->pheanstalk->{$method}();

            if ($job !== null) {
                $jobs[] = $this->makeJob($queue, $job, $jobStatus);
            }
        }

        if (($status === null || $status === 'completed') && $this->historyAvailable()) {
            $rows = $this->historyQuery()
                ->where('queue', $queue)
                ->where('status', 'completed')
                ->orderByDesc('finished_at')
                ->limit(min($limit, 500))
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

        return array_slice($jobs, 0, min($limit, 500));
    }

    public function retry(string $jobId, ?string $queue = null): void
    {
        $job = $this->pheanstalk->peek($this->jobId($jobId));
        $this->pheanstalk->kickJob($job->getId());
    }

    public function delete(string $jobId, ?string $queue = null): void
    {
        $job = $this->pheanstalk->peek($this->jobId($jobId));
        $this->pheanstalk->delete($job);
    }

    private function makeJob(string $queue, object $job, string $status): QueueJob
    {
        $rawPayload = (string) $job->getData();
        $payload = json_decode($rawPayload, true);
        $id = $job->getId();
        $jobId = method_exists($id, 'getId') ? (string) $id->getId() : (string) $id;

        return new QueueJob(
            id: $jobId,
            connection: $this->connectionName,
            queue: $queue,
            status: $status,
            name: is_array($payload) ? ($payload['displayName'] ?? $payload['job'] ?? null) : null,
            payload: is_array($payload) ? $this->maskPayload($payload) : null,
            attempts: is_array($payload) && isset($payload['attempts']) ? (int) $payload['attempts'] : null,
            createdAt: is_array($payload) && isset($payload['createdAt']) ? (int) $payload['createdAt'] : null,
        );
    }

    private function tube(string $name): object
    {
        $class = 'Pheanstalk\\Values\\TubeName';

        return new $class($name);
    }

    private function jobId(string $id): object
    {
        $class = 'Pheanstalk\\Values\\JobId';

        return new $class($id);
    }

    private function maskPayload(array $payload): array
    {
        if (config('queue-monitor.payload.mask_sensitive_data', true) === false) {
            return $payload;
        }

        $masker = $this->payloadMasker ?? new PayloadMasker;

        return $masker->mask($payload, (array) config('queue-monitor.payload.sensitive_keys', []));
    }

    private function historyAvailable(): bool
    {
        return (bool) config('queue-monitor.history.enabled', false)
            && Schema::connection($this->historyDatabase)->hasTable($this->historyTable);
    }

    private function historyQuery(): Builder
    {
        return DB::connection($this->historyDatabase)->table($this->historyTable)
            ->where('connection', $this->connectionName);
    }
}
