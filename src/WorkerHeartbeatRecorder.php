<?php

namespace MertAydin\LaravelQueueMonitor;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStarting;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\DB;

final class WorkerHeartbeatRecorder
{
    private ?string $workerKey = null;

    public function starting(WorkerStarting $event): void
    {
        $this->workerKey = $this->key();
        $now = CarbonImmutable::now();

        $this->table()->updateOrInsert(
            ['worker_key' => $this->workerKey],
            [
                'hostname' => gethostname() ?: 'unknown',
                'process_id' => getmypid() ?: 0,
                'connection' => (string) $event->connectionName,
                'queue' => (string) $event->queue,
                'status' => 'active',
                'started_at' => $now,
                'heartbeat_at' => $now,
                'last_job_at' => null,
                'stopped_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    public function looping(Looping $event): void
    {
        $this->touch((string) $event->connectionName, (string) $event->queue, false);
    }

    public function jobProcessing(JobProcessing $event): void
    {
        $queue = method_exists($event->job, 'getQueue') ? (string) $event->job->getQueue() : 'default';
        $this->touch((string) $event->connectionName, $queue, true);
    }

    public function stopping(WorkerStopping $event): void
    {
        if ($this->workerKey === null) {
            return;
        }

        $now = CarbonImmutable::now();
        $this->table()->where('worker_key', $this->workerKey)->update([
            'status' => 'stopped',
            'heartbeat_at' => $now,
            'stopped_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function touch(string $connection, string $queue, bool $jobStarted): void
    {
        $key = $this->workerKey ?? $this->key();
        $now = CarbonImmutable::now();
        $values = [
            'connection' => $connection,
            'queue' => $queue,
            'status' => 'active',
            'heartbeat_at' => $now,
            'updated_at' => $now,
        ];

        if ($jobStarted) {
            $values['last_job_at'] = $now;
        }

        $this->table()->updateOrInsert(
            ['worker_key' => $key],
            $values + [
                'hostname' => gethostname() ?: 'unknown',
                'process_id' => getmypid() ?: 0,
                'started_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    private function key(): string
    {
        return (gethostname() ?: 'unknown').':'.(getmypid() ?: 0);
    }

    private function table(): Builder
    {
        return DB::connection(config('queue-monitor.workers.connection'))
            ->table('queue_monitor_workers');
    }
}
