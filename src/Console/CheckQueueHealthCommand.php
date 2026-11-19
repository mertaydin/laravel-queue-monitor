<?php

namespace MertAydin\LaravelQueueMonitor\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MertAydin\LaravelQueueMonitor\Alerts\QueueAlertDispatcher;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;
use Throwable;

final class CheckQueueHealthCommand extends Command
{
    protected $signature = 'queue-monitor:check-health';

    protected $description = 'Check queue health thresholds and dispatch configured alerts.';

    public function handle(QueueDriverManager $manager, QueueAlertDispatcher $dispatcher): int
    {
        if (! (bool) config('queue-monitor.notifications.enabled', false)) {
            $this->info('Queue Monitor notifications are disabled.');

            return self::SUCCESS;
        }

        $backlogLimit = max(0, (int) config('queue-monitor.health.backlog_warning', 1000));

        foreach ($manager->all() as $connection => $driver) {
            try {
                $snapshot = $driver->snapshot();

                if ($backlogLimit > 0 && $snapshot->pending >= $backlogLimit) {
                    $this->sendAlert($dispatcher, 'backlog:'.$connection, [
                        'event' => 'queue.backlog_threshold',
                        'message' => 'Queue backlog threshold reached.',
                        'connection' => $connection,
                        'pending' => $snapshot->pending,
                        'threshold' => $backlogLimit,
                        'occurred_at' => now()->toIso8601String(),
                    ]);
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        $this->checkStaleWorkers($dispatcher);
        $this->checkFailureRate($dispatcher);
        $this->checkQueueSla($dispatcher);
        $this->info('Queue health checks completed.');

        return self::SUCCESS;
    }

    private function checkStaleWorkers(QueueAlertDispatcher $dispatcher): void
    {
        if (! (bool) config('queue-monitor.health.stale_worker_warning', true)) {
            return;
        }

        try {
            $connection = config('queue-monitor.workers.connection');
            $table = 'queue_monitor_workers';

            if (! Schema::connection($connection)->hasTable($table)) {
                return;
            }

            $staleAfter = max(1, (int) config('queue-monitor.workers.stale_after_seconds', 90));
            $staleCount = DB::connection($connection)->table($table)
                ->where('status', '!=', 'stopped')
                ->where('heartbeat_at', '<', now()->subSeconds($staleAfter))
                ->count();

            if ($staleCount > 0) {
                $this->sendAlert($dispatcher, 'stale-workers', [
                    'event' => 'queue.stale_workers',
                    'message' => 'One or more queue workers have stale heartbeats.',
                    'stale_workers' => $staleCount,
                    'threshold_seconds' => $staleAfter,
                    'occurred_at' => now()->toIso8601String(),
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function checkQueueSla(QueueAlertDispatcher $dispatcher): void
    {
        if (! (bool) config('queue-monitor.sla.enabled', true) || ! (bool) config('queue-monitor.history.enabled', false)) {
            return;
        }

        try {
            $connection = config('queue-monitor.history.connection');
            $table = config('queue-monitor.history.table', 'queue_monitor_jobs');
            if (! Schema::connection($connection)->hasTable($table)
                || ! Schema::connection($connection)->hasColumn($table, 'queued_at')) {
                return;
            }

            $records = DB::connection($connection)->table($table)
                ->where('status', 'pending')->whereNotNull('queued_at')
                ->orderBy('queued_at')->limit(10000)->get(['connection', 'queue', 'queued_at']);
            $thresholds = (array) config('queue-monitor.sla.queues', []);
            $defaultThreshold = max(1, (int) config('queue-monitor.sla.default_wait_seconds', 30));
            $breaches = [];

            foreach ($records as $record) {
                $threshold = max(1, (int) ($thresholds[$record->queue] ?? $defaultThreshold));
                $wait = max(0, now()->getTimestamp() - strtotime((string) $record->queued_at));
                if ($wait <= $threshold) {
                    continue;
                }

                $key = $record->connection.':'.$record->queue;
                $breaches[$key] ??= [
                    'connection' => $record->connection, 'queue' => $record->queue,
                    'count' => 0, 'oldest_wait_seconds' => 0, 'threshold_seconds' => $threshold,
                ];
                $breaches[$key]['count']++;
                $breaches[$key]['oldest_wait_seconds'] = max($breaches[$key]['oldest_wait_seconds'], $wait);
            }

            foreach ($breaches as $key => $breach) {
                $this->sendAlert($dispatcher, 'sla:'.$key, [
                    'event' => 'queue.sla_breach',
                    'message' => 'Pending jobs have exceeded the configured queue wait-time target.',
                    ...$breach,
                    'occurred_at' => now()->toIso8601String(),
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function checkFailureRate(QueueAlertDispatcher $dispatcher): void
    {
        if (! (bool) config('queue-monitor.history.enabled', false)) {
            return;
        }

        try {
            $connection = config('queue-monitor.history.connection');
            $table = config('queue-monitor.history.table', 'queue_monitor_jobs');

            if (! Schema::connection($connection)->hasTable($table)) {
                return;
            }

            $since = now()->subMinutes(max(1, (int) config('queue-monitor.health.failure_rate_window_minutes', 15)));
            $query = DB::connection($connection)->table($table)->where('finished_at', '>=', $since);
            $completed = (clone $query)->where('status', 'completed')->count();
            $failed = (clone $query)->where('status', 'failed')->count();
            $total = $completed + $failed;
            $limit = max(0, min(1, (float) config('queue-monitor.health.failure_rate_warning', 0.05)));

            if ($total > 0 && $failed / $total >= $limit) {
                $this->sendAlert($dispatcher, 'failure-rate', [
                    'event' => 'queue.failure_rate_threshold',
                    'message' => 'Queue job failure rate exceeded the configured threshold.',
                    'completed' => $completed,
                    'failed' => $failed,
                    'failure_rate' => round($failed / $total, 4),
                    'threshold' => $limit,
                    'window_minutes' => (int) config('queue-monitor.health.failure_rate_window_minutes', 15),
                    'occurred_at' => now()->toIso8601String(),
                ]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** @param array<string, mixed> $payload */
    private function sendAlert(QueueAlertDispatcher $dispatcher, string $key, array $payload): void
    {
        $cooldown = max(1, (int) config('queue-monitor.notifications.cooldown_minutes', 10));

        if (Cache::add('queue-monitor:alert:'.$key, true, $cooldown * 60)) {
            $dispatcher->dispatch($payload);
            $this->line((string) $payload['event']);
        }
    }
}
