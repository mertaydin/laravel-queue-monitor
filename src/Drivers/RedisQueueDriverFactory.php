<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

use Illuminate\Support\Facades\Redis;

final class RedisQueueDriverFactory
{
    public function make(string $connectionName, array $queueConfiguration): RedisQueueDriver
    {
        $monitorConfiguration = (array) config("queue-monitor.connections.{$connectionName}", []);
        $historyConfiguration = (array) config('queue-monitor.history', []);
        $queues = $monitorConfiguration['queues'] ?? [$queueConfiguration['queue'] ?? 'default'];

        if (is_string($queues)) {
            $queues = array_map('trim', explode(',', $queues));
        }

        return new RedisQueueDriver(
            connectionName: $connectionName,
            redis: Redis::connection($queueConfiguration['connection'] ?? 'default'),
            queues: (array) $queues,
            queuePrefix: (string) ($monitorConfiguration['queue_prefix'] ?? 'queues:'),
            historyDatabase: $historyConfiguration['connection'] ?? null,
            historyTable: $historyConfiguration['table'] ?? 'queue_monitor_jobs',
        );
    }
}
