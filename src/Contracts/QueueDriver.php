<?php

namespace MertAydin\LaravelQueueMonitor\Contracts;

use MertAydin\LaravelQueueMonitor\Data\QueueCapabilities;
use MertAydin\LaravelQueueMonitor\Data\QueueJob;
use MertAydin\LaravelQueueMonitor\Data\QueueSnapshot;

interface QueueDriver
{
    public function name(): string;

    public function capabilities(): QueueCapabilities;

    /** @return list<string> */
    public function queues(): array;

    public function snapshot(?string $queue = null): QueueSnapshot;

    /** @return list<QueueJob> */
    public function jobs(string $queue, ?string $status = null, int $limit = 50): array;

    public function retry(string $jobId, ?string $queue = null): void;

    public function delete(string $jobId, ?string $queue = null): void;
}
