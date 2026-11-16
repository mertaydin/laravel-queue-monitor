<?php

return [
    'path' => env('QUEUE_MONITOR_PATH', 'queue-monitor'),

    'authorization' => [
        'gate' => 'viewQueueMonitor',
    ],

    'history' => [
        'enabled' => false,
        'connection' => env('QUEUE_MONITOR_DB_CONNECTION'),
        'table' => 'queue_monitor_jobs',
        'retention_days' => 14,
    ],

    'payload' => [
        'mask_sensitive_data' => true,
        'sensitive_keys' => [
            'password',
            'password_confirmation',
            'token',
            'access_token',
            'refresh_token',
            'api_key',
            'secret',
            'authorization',
        ],
    ],

    'health' => [
        'backlog_warning' => 1000,
        'failure_rate_warning' => 0.05,
        'failure_rate_window_minutes' => 15,
        'stale_worker_warning' => true,
    ],

    'retry' => [
        // Zero means unlimited manual retries.
        'max_attempts' => 0,
        'max_attempts_by_queue' => [],
    ],

    'notifications' => [
        'enabled' => false,
        'timeout_seconds' => 3,
        'cooldown_minutes' => 10,
        'webhooks' => [],
    ],

    'supervisor' => [
        'servers' => [],
    ],

    'workers' => [
        'connection' => env('QUEUE_MONITOR_WORKERS_DB_CONNECTION'),
        'stale_after_seconds' => 90,
    ],

    'connections' => [],
];
