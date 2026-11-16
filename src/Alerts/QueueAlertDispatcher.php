<?php

namespace MertAydin\LaravelQueueMonitor\Alerts;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use MertAydin\LaravelQueueMonitor\Events\QueueAlertTriggered;
use Throwable;

final class QueueAlertDispatcher
{
    public function jobFailed(JobFailed $event): void
    {
        if (! (bool) config('queue-monitor.notifications.enabled', false)) {
            return;
        }

        $job = $event->job;
        $payload = [
            'event' => 'queue.job_failed',
            'message' => 'A queue job has failed.',
            'connection' => (string) $event->connectionName,
            'queue' => method_exists($job, 'getQueue') ? (string) $job->getQueue() : 'default',
            'job_id' => method_exists($job, 'getJobId') ? (string) $job->getJobId() : null,
            'job_name' => method_exists($job, 'resolveName') ? $job->resolveName() : null,
            'attempts' => method_exists($job, 'attempts') ? (int) $job->attempts() : null,
            'exception' => (string) ($event->exception ?? 'Unknown exception'),
            'occurred_at' => now()->toIso8601String(),
        ];

        $this->dispatch($payload);
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(array $payload): void
    {
        if (! (bool) config('queue-monitor.notifications.enabled', false)) {
            return;
        }

        Event::dispatch(new QueueAlertTriggered($payload));

        $timeout = max(1, (int) config('queue-monitor.notifications.timeout_seconds', 3));

        foreach ((array) config('queue-monitor.notifications.webhooks', []) as $url) {
            if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            try {
                Http::timeout($timeout)->post($url, $payload);
            } catch (Throwable) {
                // Alert delivery must never interrupt queue worker execution.
            }
        }
    }
}
