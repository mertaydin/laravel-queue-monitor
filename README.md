# Laravel Queue Monitor

A Laravel package for monitoring and managing queues across supported queue connections.

> **Status:** Early development. APIs and configuration may change.

## Current capabilities

- A driver-aware contract for queue adapters.
- A registry for resolving adapters by Laravel queue connection.
- Optional job lifecycle history for processing, completed, and failed jobs.
- Recursive masking of configured sensitive payload keys before history is persisted.
- A configurable history table and database connection.

The lifecycle recorder listens to Laravel queue events. Completed jobs are only retained when history recording is enabled; queue drivers normally remove successful jobs after processing.

## Installation

Install the package through Composer:

```bash
composer require mertaydin/laravel-queue-monitor
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=queue-monitor-config
```

Enable lifecycle history in `config/queue-monitor.php`:

```php
'history' => [
    'enabled' => true,
    'connection' => env('QUEUE_MONITOR_DB_CONNECTION'),
    'table' => 'queue_monitor_jobs',
    'retention_days' => 14,
],
```

Then run migrations:

```bash
php artisan migrate
```

When enabled, the package records jobs as they start, complete, or fail. Payload keys listed in `payload.sensitive_keys` are replaced recursively with `[REDACTED]` before being stored. Set `payload.mask_sensitive_data` to `false` only if storing unmasked payloads is acceptable for your environment.

Job history is stored in the configured database connection and table. The recorder preserves the original creation timestamp across lifecycle updates and identifies records by connection plus queue job ID.

## Design principles

- **Capability-aware:** Driver-specific limitations are explicit; unsupported data is never presented as available.
- **History-aware:** Completed-job history is opt-in and stored by the package.
- **Secure by default:** Dashboard access will be protected by an authorization gate; payload masking is enabled by default.
- **Laravel-native:** Integrates with Laravel queue events, configuration, service container, and conventions.
- **Maintainable:** Small contracts, focused adapters, clear naming, and automated tests.

## Planned driver targets

- Database
- Redis
- Beanstalkd
- Amazon SQS
- Sync (event-based visibility; no persistent queue depth)

Each driver will document and test its actual capabilities.

## Development

Run the test suite with:

```bash
composer install
composer test
```

## License

MIT
