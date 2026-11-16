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

        foreach ((array) config('queue-monitor.notifications.webhooks', []) as $webhook) {
            $configuration = is_string($webhook) ? ['driver' => 'generic', 'url' => $webhook] : $webhook;

            if (! is_array($configuration)) {
                continue;
            }

            $url = $configuration['url'] ?? null;
            $driver = $configuration['driver'] ?? 'generic';

            if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            $message = (string) ($payload['message'] ?? 'Queue alert');
            $context = array_filter([
                'event' => $payload['event'] ?? null,
                'connection' => $payload['connection'] ?? null,
                'queue' => $payload['queue'] ?? null,
                'job' => $payload['job_name'] ?? null,
            ]);
            $text = $message.($context === [] ? '' : "\n".implode(' · ', array_map(
                static fn (string $key, mixed $value): string => $key.': '.$value,
                array_keys($context),
                array_values($context)
            )));

            $body = match ($driver) {
                'slack', 'teams' => ['text' => $text],
                'discord' => ['content' => $text],
                'telegram' => [
                    'chat_id' => $configuration['chat_id'] ?? null,
                    'text' => $text,
                ],
                default => $payload,
            };

            if ($driver === 'telegram' && empty($body['chat_id'])) {
                continue;
            }

            try {
                Http::timeout($timeout)->post($url, $body);
            } catch (Throwable) {
                // Alert delivery must never interrupt queue worker execution.
            }
        }
    }
}
