<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

final class LazyRedisConnection
{
    public function __construct(
        private readonly object $redisManager,
        private readonly string $connectionName,
    ) {}

    public function command(string $command, array $parameters = []): mixed
    {
        return $this->redisManager
            ->connection($this->connectionName)
            ->command($command, $parameters);
    }
}
