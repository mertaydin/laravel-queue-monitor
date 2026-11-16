<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

use Illuminate\Support\Facades\Queue;
use RuntimeException;

final class BeanstalkdQueueDriverFactory
{
    public function make(string $connectionName, array $queueConfiguration): BeanstalkdQueueDriver
    {
        $queue = Queue::connection($connectionName);

        if (method_exists($queue, 'getPheanstalk') === false) {
            throw new RuntimeException(sprintf(
                'The configured queue connection [%s] does not expose a Pheanstalk client.',
                $connectionName
            ));
        }

        $historyConfiguration = config('queue-monitor.history', []);
        $defaultQueue = (string) ($queueConfiguration['queue'] ?? 'default');
        $configuredQueues = (array) config(
            'queue-monitor.connections.'.$connectionName.'.queues',
            [$defaultQueue]
        );

        return new BeanstalkdQueueDriver(
            connectionName: $connectionName,
            pheanstalk: $queue->getPheanstalk(),
            configuredQueues: $configuredQueues,
            historyDatabase: $historyConfiguration['connection'] ?? null,
            historyTable: $historyConfiguration['table'] ?? 'queue_monitor_jobs',
        );
    }
}
