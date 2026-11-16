<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Feature;

use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class HealthAlertCommandTest extends TestCase
{
    public function test_health_check_command_exits_cleanly_when_notifications_are_disabled(): void
    {
        config(['queue-monitor.notifications.enabled' => false]);

        $this->artisan('queue-monitor:check-health')->assertExitCode(0);
    }
}
