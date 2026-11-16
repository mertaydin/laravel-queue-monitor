<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

use Illuminate\Support\Facades\Queue;

final class BeanstalkdQueueDriverFactory
{
    public function make(string $connectionName, array $queueConfiguration): BeanstalkdQueueDriver
    {
        $historyConfiguration = config('queue-monitor.history', []);
        $defaultQueue = (string) ($queueConfiguration['queue'] ?? 'default');
        $configuredQueues = (array) config(
            'queue-monitor.connections.'.$connectionName.'.queues',
            [$defaultQueue]
        );

        return new BeanstalkdQueueDriver(
            connectionName: $connectionName,
            pheanstalk: new LazyBeanstalkdConnection(
                static fn (): object => Queue::connection($connectionName)->getPheanstalk()
            ),
            configuredQueues: $configuredQueues,
            historyDatabase: $historyConfiguration['connection'] ?? null,
            historyTable: $historyConfiguration['table'] ?? 'queue_monitor_jobs',
        );
    }
}
