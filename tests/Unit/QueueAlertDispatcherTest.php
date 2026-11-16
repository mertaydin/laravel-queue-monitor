<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Unit;

use Illuminate\Support\Facades\Http;
use MertAydin\LaravelQueueMonitor\Alerts\QueueAlertDispatcher;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class QueueAlertDispatcherTest extends TestCase
{
    public function test_discord_webhook_receives_a_provider_compatible_message(): void
    {
        Http::fake();
        config([
            'queue-monitor.notifications.enabled' => true,
            'queue-monitor.notifications.webhooks' => [
                ['driver' => 'discord', 'url' => 'https://discord.example.test/webhook'],
            ],
        ]);

        app(QueueAlertDispatcher::class)->dispatch([
            'event' => 'queue.backlog_threshold',
            'message' => 'Queue backlog threshold reached.',
            'connection' => 'redis',
            'queue' => 'emails',
        ]);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://discord.example.test/webhook'
            && str_contains($request['content'], 'Queue backlog threshold reached.')
            && str_contains($request['content'], 'queue: emails'));
    }

    public function test_telegram_webhook_is_skipped_without_a_chat_id(): void
    {
        Http::fake();
        config([
            'queue-monitor.notifications.enabled' => true,
            'queue-monitor.notifications.webhooks' => [
                ['driver' => 'telegram', 'url' => 'https://api.telegram.org/bot123/sendMessage'],
            ],
        ]);

        app(QueueAlertDispatcher::class)->dispatch(['message' => 'Alert']);

        Http::assertNothingSent();
    }
}
