<?php

namespace MertAydin\LaravelQueueMonitor\Data;

final class QueueJob
{
    public function __construct(
        public readonly string $id,
        public readonly string $connection,
        public readonly string $queue,
        public readonly string $status,
        public readonly ?string $name = null,
        public readonly ?array $payload = null,
        public readonly ?int $attempts = null,
        public readonly ?int $createdAt = null,
        public readonly ?int $reservedAt = null,
        public readonly ?int $completedAt = null,
        public readonly ?int $durationMilliseconds = null,
        public readonly ?string $exception = null,
    ) {}
}
