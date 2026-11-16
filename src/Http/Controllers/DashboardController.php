<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;
use Throwable;

final class DashboardController
{
    public function __invoke(Request $request, QueueDriverManager $manager): View
    {
        $gate = (string) config('queue-monitor.authorization.gate', 'viewQueueMonitor');

        Gate::authorize($gate);

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
                $slowest = $records->where('status', 'completed')
                    ->filter(fn ($record) => $record->duration_ms !== null)
                    ->sortByDesc('duration_ms')->take(5)->values();

                $analytics = [
                    'available' => true,
                    'labels' => array_column(array_values($buckets), 'label'),
                    'completed' => array_column(array_values($buckets), 'completed'),
                    'failed' => array_column(array_values($buckets), 'failed'),
                    'average_duration' => $averageDuration,
                    'p95_duration' => $p95Duration,
                    'slowest' => $slowest,
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
            'period' => $period,
            'search' => trim((string) $request->query('search', '')),
        ]);
    }
}
