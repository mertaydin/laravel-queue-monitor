# Laravel Queue Monitor

A Laravel package for monitoring and managing queues across supported queue connections.

> **Status:** Early development. APIs and configuration may change.

## Current capabilities

- Database queues: pending, reserved, failed, and recorded completed jobs.
- Redis queues: pending, reserved, delayed, and recorded completed jobs.
- Queue depth snapshots, payload inspection, retry, and delete operations.
- Driver-aware adapters and automatic registration of supported Laravel queue connections.
- Optional lifecycle history and recursive masking of sensitive payload keys.

Completed jobs are retained only when package history is enabled; Laravel normally removes successful jobs after processing.

## Installation

```bash
composer require mertaydin/laravel-queue-monitor
php artisan vendor:publish --tag=queue-monitor-config
php artisan migrate
```

Enable history in `config/queue-monitor.php`:

```php
'history' => [
    'enabled' => true,
    'connection' => env('QUEUE_MONITOR_DB_CONNECTION'),
    'table' => 'queue_monitor_jobs',
    'retention_days' => 14,
],
```

## Redis configuration

Redis connections are registered automatically. Queue names default to the Laravel connection's configured `queue` value. Add other names explicitly:

```php
'connections' => [
    'redis' => [
        'queues' => ['default', 'emails', 'reports'],
        'queue_prefix' => 'queues:',
    ],
],
```

The adapter reads Laravel Redis pending lists and reserved/delayed sorted sets. Completed jobs require package history. Failed jobs are stored by Laravel's failed-job provider outside Redis, so failed-job inspection is not advertised for this adapter. Queue discovery uses configured queue names and does not issue Redis `KEYS`.

Retry moves a matching reserved or delayed payload back to its pending list; already-pending jobs remain unchanged. Delete removes a matching payload from its current Redis structure. Payload masking is enabled by default.

## Design principles

- **Capability-aware:** Unsupported features are not presented as available.
- **History-aware:** Completed-job history is opt-in.
- **Secure by default:** Sensitive payload values are masked by default.
- **Laravel-native:** Uses Laravel queue events, configuration, and service container.

## Planned driver targets

- Database
- Redis
- Beanstalkd
- Amazon SQS
- Sync (event-based visibility; no persistent queue depth)

## Development

```bash
composer install
composer test
```

## License

MIT
