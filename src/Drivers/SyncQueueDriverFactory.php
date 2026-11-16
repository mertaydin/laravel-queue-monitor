<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

final class SyncQueueDriverFactory
{
    public function make(string $connectionName, array $queueConfiguration): SyncQueueDriver
    {
        $historyConfiguration = config('queue-monitor.history', []);
        $defaultQueue = (string) ($queueConfiguration['queue'] ?? 'default');
        $configuredQueues = (array) config(
            'queue-monitor.connections.'.$connectionName.'.queues',
            [$defaultQueue]
        );

        return new SyncQueueDriver(
            connectionName: $connectionName,
            configuredQueues: $configuredQueues,
            historyDatabase: $historyConfiguration['connection'] ?? null,
            historyTable: $historyConfiguration['table'] ?? 'queue_monitor_jobs',
        );
    }
}
