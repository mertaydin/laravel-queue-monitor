<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;
use Throwable;

final class QueueComparisonController
{
    public function __invoke(QueueDriverManager $manager): View
    {
        Gate::authorize((string) config('queue-monitor.authorization.gate', 'viewQueueMonitor'));

        $rows = [];

        foreach ($manager->all() as $connection => $driver) {
            try {
                foreach ($driver->queues() as $queue) {
                    $snapshot = $driver->snapshot($queue);
                    $rows[] = [
                        'connection' => $connection,
                        'driver' => $driver->name(),
                        'queue' => $queue,
                        'pending' => $snapshot->pending,
                        'running' => $snapshot->running,
                        'completed' => $snapshot->completed,
                        'failed' => $snapshot->failed,
                        'error' => false,
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);
                $rows[] = [
                    'connection' => $connection,
                    'driver' => $driver->name(),
                    'queue' => '—',
                    'pending' => 0,
                    'running' => 0,
                    'completed' => 0,
                    'failed' => 0,
                    'error' => true,
                ];
            }
        }

        usort($rows, static fn (array $left, array $right): int => $right['pending'] <=> $left['pending']);

        return view('queue-monitor::queue-comparison', ['rows' => $rows]);
    }
}
