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
    ],

    'connections' => [],
];
