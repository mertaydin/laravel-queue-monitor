<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

use Illuminate\Support\Facades\Queue;

final class SqsQueueDriverFactory
{
    public function make(string $connectionName, array $queueConfiguration): SqsQueueDriver
    {
        $historyConfiguration = config('queue-monitor.history', []);
        $defaultQueue = (string) ($queueConfiguration['queue'] ?? 'default');
        $configuredQueues = (array) config(
            'queue-monitor.connections.'.$connectionName.'.queues',
            [$defaultQueue]
        );

        return new SqsQueueDriver(
            connectionName: $connectionName,
            queueConnection: Queue::connection($connectionName),
            configuredQueues: $configuredQueues,
            historyDatabase: $historyConfiguration['connection'] ?? null,
            historyTable: $historyConfiguration['table'] ?? 'queue_monitor_jobs',
        );
    }
}
