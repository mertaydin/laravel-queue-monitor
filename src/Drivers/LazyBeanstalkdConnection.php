<?php

namespace MertAydin\LaravelQueueMonitor\Drivers;

use Closure;
use RuntimeException;

final class LazyBeanstalkdConnection
{
    private ?object $connection = null;

    public function __construct(private readonly Closure $resolver)
    {
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->connection()->{$method}(...$arguments);
    }

    private function connection(): object
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $connection = ($this->resolver)();

        if (is_object($connection) === false) {
            throw new RuntimeException('The Beanstalkd connection resolver must return an object.');
        }

        return $this->connection = $connection;
    }
}
