<?php

namespace MertAydin\LaravelQueueMonitor\Tests\Unit;

use MertAydin\LaravelQueueMonitor\Support\PayloadMasker;
use MertAydin\LaravelQueueMonitor\Tests\TestCase;

class PayloadMaskerTest extends TestCase
{
    public function test_it_masks_sensitive_keys_recursively_without_changing_other_values(): void
    {
        $payload = [
            'displayName' => 'SendNotification',
            'data' => [
                'email' => 'person@example.com',
                'token' => 'secret-token',
                'nested' => ['Password' => 'secret-password'],
            ],
        ];

        $masked = (new PayloadMasker())->mask($payload, ['token', 'password']);

        $this->assertSame('SendNotification', $masked['displayName']);
        $this->assertSame('person@example.com', $masked['data']['email']);
        $this->assertSame('[REDACTED]', $masked['data']['token']);
        $this->assertSame('[REDACTED]', $masked['data']['nested']['Password']);
        $this->assertSame('secret-token', $payload['data']['token']);
    }
}
