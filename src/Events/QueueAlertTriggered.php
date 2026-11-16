<?php

namespace MertAydin\LaravelQueueMonitor\Events;

final class QueueAlertTriggered
{
    public function __construct(public readonly array $payload) {}
}
