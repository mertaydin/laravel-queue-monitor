<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

use Illuminate\Support\Facades\DB;

final class DatabaseQueueDriverFactory
{
    public function make(string $connectionName, array $queueConfiguration): DatabaseQueueDriver
    {
        $failedConfiguration = config('queue.failed', []);
        $historyConfiguration = config('queue-monitor.history', []);

        return new DatabaseQueueDriver(
            connectionName: $connectionName,
            database: DB::connection($queueConfiguration['connection'] ?? null),
            jobsTable: $queueConfiguration['table'] ?? 'jobs',
            failedDatabase: $failedConfiguration['database'] ?? null,
            failedJobsTable: $failedConfiguration['table'] ?? 'failed_jobs',
            historyDatabase: $historyConfiguration['connection'] ?? null,
            historyTable: $historyConfiguration['table'] ?? 'queue_monitor_jobs',
        );
    }
}
