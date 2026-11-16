# Laravel Queue Monitor

A Laravel package for monitoring and managing queues across supported queue connections.

> **Status:** Early development. APIs and configuration may change.

## Current capabilities

- Database queues: pending, reserved, failed, and recorded completed jobs.
- Redis queues: pending, reserved, delayed, and recorded completed jobs.
- Beanstalkd queues: ready, reserved, delayed, buried, and recorded completed counts.
- Queue depth snapshots, payload inspection, retry, and delete operations.
- Driver-aware adapters and automatic registration of supported Laravel queue connections.
- Optional lifecycle history and recursive masking of sensitive payload keys.

Completed jobs are retained only when package history is enabled; Laravel normally removes successful jobs after processing.

When history is enabled, the dashboard includes 24-hour, 7-day and 30-day activity summaries, average and 95th-percentile completed-job duration, a slowest-jobs list, and a queue-health indicator. Analytics are calculated from recorded history and are intentionally unavailable when history is disabled. Configure health warnings with `health.backlog_warning` and `health.failure_rate_warning` in `config/queue-monitor.php`.

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

## Beanstalkd support

Install Laravel's optional Beanstalkd client dependency in the host application:

```bash
composer require pda/pheanstalk:^5.0
```

Beanstalkd tubes are discovered from the server. Queue snapshots include ready, reserved, delayed, and buried counts; buried jobs are represented as failed. The protocol can report the reserved count but does not provide a way to list reserved job payloads, so running-job inspection is not advertised. The Pheanstalk peek commands expose only the next ready, delayed, or buried job in a tube; this adapter therefore returns at most one inspectable job per state and tube. Retrying uses Beanstalkd's kick operation for buried or delayed jobs. Completed-job details require package history.

You can add configured tube names as a fallback in `config/queue-monitor.php`:

```php
'connections' => [
    'beanstalkd' => [
        'queues' => ['default', 'emails'],
    ],
],
```

## Dashboard

The package includes a responsive dashboard at `/queue-monitor`. Publish its stylesheet after installation:

```bash
php artisan vendor:publish --tag=queue-monitor-assets
```

Access is denied by default unless the configured authorization gate exists and grants access. Define the default gate in your application, for example:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewQueueMonitor', fn ($user) => $user !== null && $user->is_admin);
```

You can change the route path with `QUEUE_MONITOR_PATH` and the gate name with the `authorization.gate` setting. The dashboard adapts its available job states and actions to each driver's capabilities. Retry and delete controls are shown only when supported for the selected job state. Mutations use POST/DELETE routes, Laravel CSRF protection and the same authorization gate as dashboard access.

## Design principles

- **Capability-aware:** Unsupported features are not presented as available.
- **History-aware:** Completed-job history is opt-in.
- **Secure by default:** Sensitive payload values are masked by default.
- **Laravel-native:** Uses Laravel queue events, configuration, and service container.

## Planned driver targets

- Amazon SQS
- Sync (event-based visibility; no persistent queue depth)

## Development

```bash
composer install
composer test
```

## License

MIT
