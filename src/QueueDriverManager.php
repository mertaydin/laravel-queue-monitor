<?php

namespace MertAydin\LaravelQueueMonitor;

use InvalidArgumentException;
use MertAydin\LaravelQueueMonitor\Contracts\QueueDriver;

final class QueueDriverManager
{
    /** @var array<string, QueueDriver> */
    private array $drivers = [];

    public function register(string $connection, QueueDriver $driver): void
    {
        $this->drivers[$connection] = $driver;
    }

    public function driver(string $connection): QueueDriver
    {
        if (! isset($this->drivers[$connection])) {
            throw new InvalidArgumentException(
                sprintf('No queue monitor driver is registered for connection [%s].', $connection)
            );
        }

        return $this->drivers[$connection];
    }

    /** @return array<string, QueueDriver> */
    public function all(): array
    {
        return $this->drivers;
    }

    public function has(string $connection): bool
    {
        return isset($this->drivers[$connection]);
    }
}
