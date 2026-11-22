<?php

namespace MertAydin\LaravelQueueMonitor\Data;

final class QueueSnapshot
{
    public function __construct(
        public readonly int $pending = 0,
        public readonly int $running = 0,
        public readonly int $completed = 0,
        public readonly int $failed = 0,
        public readonly ?string $queue = null,
        public readonly array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'queue' => $this->queue,
            'pending' => $this->pending,
            'running' => $this->running,
            'completed' => $this->completed,
            'failed' => $this->failed,
            'metadata' => $this->metadata,
        ];
    }
}
