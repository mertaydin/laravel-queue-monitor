<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;
use Throwable;

final class DashboardController
{
    public function __invoke(Request $request, QueueDriverManager $manager): View
    {
        $drivers = $manager->all();
        $connectionNames = array_keys($drivers);
        $requestedConnection = $request->query('connection');
        $connection = is_string($requestedConnection) && isset($drivers[$requestedConnection])
            ? $requestedConnection
            : ($connectionNames[0] ?? null);
        $driver = $connection === null ? null : $drivers[$connection];
        $queues = [];
        $queue = null;
        $status = $request->query('status');
        $allowedStatuses = ['pending', 'running', 'delayed', 'failed', 'completed'];

        if (is_string($status) === false || in_array($status, $allowedStatuses, true) === false) {
            $status = null;
        }

        $snapshot = null;
        $jobs = [];
        $connectionError = false;

        if ($driver !== null) {
            try {
                $queues = $driver->queues();
                $requestedQueue = $request->query('queue');
                $queue = is_string($requestedQueue) && in_array($requestedQueue, $queues, true)
                    ? $requestedQueue
                    : ($queues[0] ?? null);

                $snapshot = $driver->snapshot($queue);

                if ($queue !== null) {
                    $allJobs = $driver->jobs($queue, $status, 500);
                    $search = trim((string) $request->query('search', ''));

                    if ($search !== '') {
                        $allJobs = array_values(array_filter($allJobs, static function ($job) use ($search): bool {
                            $haystack = strtolower(implode(' ', [
                                $job->id,
                                $job->name ?? '',
                                $job->status,
                                json_encode($job->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
                                $job->exception ?? '',
                            ]));

                            return str_contains($haystack, strtolower($search));
                        }));
                    }

                    $jobClass = trim((string) $request->query('job_class', ''));
                    $dateFrom = trim((string) $request->query('date_from', ''));
                    $dateTo = trim((string) $request->query('date_to', ''));
                    $fromTimestamp = $this->dateTimestamp($dateFrom, false);
                    $toTimestamp = $this->dateTimestamp($dateTo, true);

                    if ($jobClass !== '' || $fromTimestamp !== null || $toTimestamp !== null) {
                        $allJobs = array_values(array_filter($allJobs, static function ($job) use ($jobClass, $fromTimestamp, $toTimestamp): bool {
                            if ($jobClass !== '' && ! str_contains(strtolower($job->name ?? ''), strtolower($jobClass))) {
                                return false;
                            }

                            if ($job->createdAt === null) {
                                return $fromTimestamp === null && $toTimestamp === null;
                            }

                            if ($fromTimestamp !== null && $job->createdAt < $fromTimestamp) {
                                return false;
                            }

                            return $toTimestamp === null || $job->createdAt <= $toTimestamp;
                        }));
                    }

                    $waitingMinutes = filter_var($request->query('waiting_minutes'), FILTER_VALIDATE_INT);
                    $waitingMinutes = $waitingMinutes !== false && in_array($waitingMinutes, [5, 15, 30, 60], true)
                        ? $waitingMinutes
                        : null;

                    if ($waitingMinutes !== null) {
                        $waitingBefore = CarbonImmutable::now()->subMinutes($waitingMinutes)->timestamp;
                        $allJobs = array_values(array_filter($allJobs, static function ($job) use ($waitingBefore): bool {
                            return in_array($job->status, ['pending', 'delayed'], true)
                                && $job->createdAt !== null
                                && (int) $job->createdAt <= $waitingBefore;
                        }));
                    }

                    $perPage = 25;
                    $currentPage = max(1, LengthAwarePaginator::resolveCurrentPage());
                    $pageItems = array_slice($allJobs, ($currentPage - 1) * $perPage, $perPage);
                    $jobs = new LengthAwarePaginator(
                        $pageItems,
                        count($allJobs),
                        $perPage,
                        $currentPage,
                        ['path' => $request->url(), 'query' => $request->query()]
                    );
                }
            } catch (Throwable) {
                $connectionError = true;
            }
        }

        $capabilities = [];

        if ($driver !== null && $connectionError === false) {
            try {
                $capabilities = $driver->capabilities()->toArray();
            } catch (Throwable) {
                $connectionError = true;
            }
        }

        $period = $request->query('period', '7d');
        $period = in_array($period, ['24h', '7d', '30d'], true) ? $period : '7d';
        $analytics = [
            'available' => false,
            'labels' => [],
            'completed' => [],
            'failed' => [],
            'average_duration' => null,
            'p95_duration' => null,
            'slowest' => [],
            'top_classes' => [],
            'completed_count' => 0,
            'failed_count' => 0,
        ];
        $health = [
            'status' => 'unknown',
            'label' => 'History unavailable',
            'message' => 'Enable persistent history to calculate queue health trends.',
        ];

        if ((bool) config('queue-monitor.history.enabled', false)) {
            $historyConnection = config('queue-monitor.history.connection');
            $historyTable = config('queue-monitor.history.table', 'queue_monitor_jobs');

            if (Schema::connection($historyConnection)->hasTable($historyTable)) {
                $since = match ($period) {
                    '24h' => CarbonImmutable::now()->subDay(),
                    '30d' => CarbonImmutable::now()->subDays(30),
                    default => CarbonImmutable::now()->subDays(7),
                };
                $historyQuery = DB::connection($historyConnection)->table($historyTable)
                    ->where('connection', $connection)
                    ->where('finished_at', '>=', $since);

                if ($queue !== null) {
                    $historyQuery->where('queue', $queue);
                }

                $records = $historyQuery->orderBy('finished_at')->limit(10000)->get([
                    'job_id', 'job_name', 'status', 'duration_ms', 'exception', 'finished_at',
                ]);
                $bucketCount = $period === '24h' ? 24 : ($period === '30d' ? 30 : 7);
                $buckets = [];

                for ($index = 0; $index < $bucketCount; $index++) {
                    $point = $period === '24h' ? $since->addHours($index) : $since->addDays($index);
                    $key = $period === '24h' ? $point->format('Y-m-d H:00') : $point->format('Y-m-d');
                    $buckets[$key] = [
                        'label' => $period === '24h' ? $point->format('H:i') : $point->format('d M'),
                        'completed' => 0,
                        'failed' => 0,
                    ];
                }

                $durations = [];

                foreach ($records as $record) {
                    $finishedAt = CarbonImmutable::parse($record->finished_at);
                    $key = $period === '24h' ? $finishedAt->format('Y-m-d H:00') : $finishedAt->format('Y-m-d');

                    if (isset($buckets[$key]) && isset($buckets[$key][$record->status])) {
                        $buckets[$key][$record->status]++;
                    }

                    if ($record->duration_ms !== null && $record->status === 'completed') {
                        $durations[] = (int) $record->duration_ms;
                    }
                }

                sort($durations);
                $completedCount = array_sum(array_column($buckets, 'completed'));
                $failedCount = array_sum(array_column($buckets, 'failed'));
                $averageDuration = $durations === [] ? null : (int) round(array_sum($durations) / count($durations));
                $p95Duration = $durations === [] ? null : $durations[(int) floor((count($durations) - 1) * 0.95)];
                $completedRecords = $records->where('status', 'completed')
                    ->filter(fn ($record) => $record->duration_ms !== null);
                $slowest = $completedRecords->sortByDesc('duration_ms')->take(5)->values();
                $topClasses = $completedRecords
                    ->groupBy(fn ($record) => $record->job_name ?: 'Unknown job')
                    ->map(fn ($jobs, $name): array => [
                        'name' => $name,
                        'count' => $jobs->count(),
                        'average_duration' => (int) round($jobs->avg('duration_ms')),
                        'max_duration' => (int) $jobs->max('duration_ms'),
                    ])
                    ->sortByDesc('average_duration')
                    ->take(5)
                    ->values()
                    ->all();

                $analytics = [
                    'available' => true,
                    'labels' => array_column(array_values($buckets), 'label'),
                    'completed' => array_column(array_values($buckets), 'completed'),
                    'failed' => array_column(array_values($buckets), 'failed'),
                    'average_duration' => $averageDuration,
                    'p95_duration' => $p95Duration,
                    'slowest' => $slowest,
                    'top_classes' => $topClasses,
                    'completed_count' => $completedCount,
                    'failed_count' => $failedCount,
                ];

                $failureRate = ($completedCount + $failedCount) > 0
                    ? $failedCount / ($completedCount + $failedCount)
                    : 0;
                $backlogLimit = (int) config('queue-monitor.health.backlog_warning', 1000);
                $failureLimit = (float) config('queue-monitor.health.failure_rate_warning', 0.05);

                if ($snapshot !== null && $backlogLimit > 0 && $snapshot->pending >= $backlogLimit) {
                    $health = [
                        'status' => 'warning',
                        'label' => 'Backlog is high',
                        'message' => 'Pending jobs have reached the configured backlog threshold.',
                    ];
                } elseif ($failureRate >= $failureLimit && ($completedCount + $failedCount) > 0) {
                    $health = [
                        'status' => 'critical',
                        'label' => 'Failure rate is elevated',
                        'message' => 'The selected period has reached the configured failure-rate threshold.',
                    ];
                } else {
                    $health = [
                        'status' => 'healthy',
                        'label' => 'Queue looks healthy',
                        'message' => 'No configured backlog or failure-rate threshold has been exceeded.',
                    ];
                }
            }
        }

        $workers = [];
        $workerCapacity = [
            'available' => false,
            'active' => 0,
            'stale' => 0,
            'stopped' => 0,
            'active_for_queue' => 0,
            'pending' => $snapshot?->pending,
            'pending_per_active_worker' => null,
            'status' => 'unavailable',
        ];
        $workerConnection = config('queue-monitor.workers.connection');

        if ($connection !== null && Schema::connection($workerConnection)->hasTable('queue_monitor_workers')) {
            $workerCapacity['available'] = true;
            $workers = DB::connection($workerConnection)->table('queue_monitor_workers')
                ->where('connection', $connection)
                ->orderByDesc('heartbeat_at')
                ->limit(100)
                ->get();

            $staleAfter = max(1, (int) config('queue-monitor.workers.stale_after_seconds', 90));
            $staleBefore = CarbonImmutable::now()->subSeconds($staleAfter);

            foreach ($workers as $worker) {
                $heartbeat = $worker->heartbeat_at === null
                    ? null
                    : CarbonImmutable::parse($worker->heartbeat_at);
                $worker->display_status = $worker->status === 'stopped'
                    ? 'stopped'
                    : ($heartbeat !== null && $heartbeat->greaterThan($staleBefore) ? 'active' : 'stale');

                $workerCapacity[$worker->display_status]++;

                if (
                    $worker->display_status === 'active'
                    && ($queue === null || $worker->queue === $queue)
                ) {
                    $workerCapacity['active_for_queue']++;
                }
            }

            if ($snapshot !== null && $workerCapacity['active_for_queue'] > 0) {
                $workerCapacity['pending_per_active_worker'] = round(
                    $snapshot->pending / $workerCapacity['active_for_queue'],
                    1
                );
            }

            $workerCapacity['status'] = ($snapshot?->pending ?? 0) > 0 && $workerCapacity['active_for_queue'] === 0
                ? 'attention'
                : 'available';
        }

        $connectionCards = [];

        foreach ($drivers as $name => $registeredDriver) {
            try {
                $connectionCards[] = [
                    'connection' => $name,
                    'driver' => $registeredDriver->name(),
                    'snapshot' => $registeredDriver->snapshot(),
                    'error' => false,
                ];
            } catch (Throwable) {
                $connectionCards[] = [
                    'connection' => $name,
                    'driver' => $registeredDriver->name(),
                    'snapshot' => null,
                    'error' => true,
                ];
            }
        }

        $supervisorServers = collect((array) config('queue-monitor.supervisor.servers', []))
            ->filter(static fn (array $server): bool => (bool) ($server['enabled'] ?? false))
            ->keys()
            ->all();

        $sla = [
            'available' => false, 'total' => 0, 'breached' => 0, 'within' => 0,
            'rate' => null, 'queues' => [],
            'window_hours' => max(1, (int) config('queue-monitor.sla.window_hours', 24)),
        ];
        $historyConnection = config('queue-monitor.history.connection');
        $historyTable = config('queue-monitor.history.table', 'queue_monitor_jobs');

        if (
            (bool) config('queue-monitor.sla.enabled', true)
            && (bool) config('queue-monitor.history.enabled', false)
            && $connection !== null
            && Schema::connection($historyConnection)->hasTable($historyTable)
            && Schema::connection($historyConnection)->hasColumn($historyTable, 'queued_at')
        ) {
            $since = CarbonImmutable::now()->subHours($sla['window_hours']);
            $query = DB::connection($historyConnection)->table($historyTable)
                ->where('connection', $connection)->whereNotNull('queued_at')->where('queued_at', '>=', $since);
            if ($queue !== null) {
                $query->where('queue', $queue);
            }

            $records = $query->limit(10000)->get(['queue', 'status', 'queued_at', 'wait_duration_ms']);
            $thresholds = (array) config('queue-monitor.sla.queues', []);
            $defaultThreshold = max(1, (int) config('queue-monitor.sla.default_wait_seconds', 30));
            $now = CarbonImmutable::now();
            $stats = [];

            foreach ($records as $record) {
                $threshold = max(1, (int) ($thresholds[$record->queue] ?? $defaultThreshold));
                $queuedAt = CarbonImmutable::parse($record->queued_at);
                $waitMs = $record->status === 'pending'
                    ? max(0, $now->getTimestampMs() - $queuedAt->getTimestampMs())
                    : ($record->wait_duration_ms === null ? null : (int) $record->wait_duration_ms);
                if ($waitMs === null) {
                    continue;
                }

                $name = (string) $record->queue;
                $stats[$name] ??= ['queue' => $name, 'threshold' => $threshold, 'total' => 0, 'breached' => 0];
                $stats[$name]['total']++;
                if ($waitMs > $threshold * 1000) {
                    $stats[$name]['breached']++;
                }
            }

            foreach ($stats as &$item) {
                $item['within'] = $item['total'] - $item['breached'];
                $item['rate'] = round(($item['within'] / $item['total']) * 100, 1);
            }
            unset($item);

            $queueStats = array_values($stats);
            $total = array_sum(array_column($queueStats, 'total'));
            $breached = array_sum(array_column($queueStats, 'breached'));
            $sla = [
                'available' => true, 'total' => $total, 'breached' => $breached,
                'within' => $total - $breached,
                'rate' => $total > 0 ? round((($total - $breached) / $total) * 100, 1) : null,
                'queues' => $queueStats, 'window_hours' => $sla['window_hours'],
            ];
        }

        return view('queue-monitor::dashboard', [
            'connection' => $connection,
            'connectionCards' => $connectionCards,
            'connectionError' => $connectionError,
            'connectionNames' => $connectionNames,
            'driverName' => $driver?->name(),
            'jobs' => $jobs,
            'queues' => $queues,
            'queue' => $queue,
            'snapshot' => $snapshot,
            'status' => $status,
            'capabilities' => $capabilities,
            'analytics' => $analytics,
            'health' => $health,
            'sla' => $sla,
            'period' => $period,
            'search' => trim((string) $request->query('search', '')),
            'jobClass' => trim((string) $request->query('job_class', '')),
            'dateFrom' => trim((string) $request->query('date_from', '')),
            'dateTo' => trim((string) $request->query('date_to', '')),
            'workers' => $workers,
            'workerCapacity' => $workerCapacity,
            'supervisorServers' => $supervisorServers,
        ]);
    }

    private function dateTimestamp(string $date, bool $endOfDay): ?int
    {
        if ($date === '' || preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) !== 1) {
            return null;
        }

        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            return null;
        }

        return $endOfDay ? $parsed->endOfDay()->timestamp : $parsed->timestamp;
    }
}
