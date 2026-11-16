<?php

namespace MertAydin\LaravelQueueMonitor\Data;

final class QueueCapabilities
{
    public function __construct(
        public readonly bool $canListQueues = false,
        public readonly bool $canInspectPending = false,
        public readonly bool $canInspectRunning = false,
        public readonly bool $canInspectCompleted = false,
        public readonly bool $canInspectFailed = false,
        public readonly bool $canRetry = false,
        public readonly bool $canDelete = false,
        public readonly bool $hasPersistentHistory = false,
    ) {
    }

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return [
            'can_list_queues' => $this->canListQueues,
            'can_inspect_pending' => $this->canInspectPending,
            'can_inspect_running' => $this->canInspectRunning,
            'can_inspect_completed' => $this->canInspectCompleted,
            'can_inspect_failed' => $this->canInspectFailed,
            'can_retry' => $this->canRetry,
            'can_delete' => $this->canDelete,
            'has_persistent_history' => $this->hasPersistentHistory,
        ];
    }
}
