# Laravel Queue Monitor

A Laravel package for monitoring and managing queues across supported queue connections.

> **Status:** Early development. APIs and configuration may change.

## Current capabilities

- Database queues: pending, reserved, failed, and recorded completed jobs.
- Redis queues: pending, reserved, delayed, and recorded completed jobs.
- Beanstalkd queues: ready, reserved, delayed, buried, and recorded completed counts.
- Amazon SQS metrics for standard and FIFO queues. Payload inspection and job actions are intentionally disabled because SQS receipt handles are short-lived.
- Sync driver history for completed and failed jobs. Queue depth and queue actions are not available.
- Optional Supervisor integration: process status, start, stop and restart for configured programs.
- Queue depth snapshots, payload inspection, retry, and delete operations.
- Driver-aware adapters and automatic registration of supported Laravel queue connections.
- Optional lifecycle history and recursive masking of sensitive payload keys.
- On-demand, read-only diagnostics for database, queue connections, worker storage and configured Supervisor servers.

Completed jobs are retained only when package history is enabled; Laravel normally removes successful jobs after processing.

When history is enabled, the dashboard includes 24-hour, 7-day and 30-day activity summaries, average and 95th-percentile completed-job duration, slowest individual jobs and the slowest job classes by average execution time, and a queue-health indicator. Analytics are calculated from recorded history and are intentionally unavailable when history is disabled. Configure health warnings with `health.backlog_warning` and `health.failure_rate_warning` in `config/queue-monitor.php`.

## Requirements

- PHP 8.2 or newer.
- Laravel 12 or 13.

Laravel 10 and 11 are outside their security support windows. They are not included in the supported version range.

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

## Amazon SQS support

SQS connections are registered automatically. Install the AWS SDK in the host application with `composer require aws/aws-sdk-php`. Configure queue names for each connection. Standard and FIFO queues use the same metrics adapter.

```php
'connections' => [
    'sqs' => [
        'queues' => ['emails', 'emails.fifo'],
    ],
],
```

The adapter reads approximate pending, in-flight and delayed counts. It does not receive messages to inspect them. This avoids changing receive counts or triggering redrive policies during dashboard viewing. Job payload inspection, retry and deletion are not supported. Completed-job history is available when history recording is enabled.

## Sync driver

The sync driver executes jobs immediately. It has no queue depth. Enable package history to inspect completed and failed jobs after execution. Pending-job inspection, retry and deletion are not available.

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

You can change the route path with `QUEUE_MONITOR_PATH` and the gate name with the `authorization.gate` setting. The dashboard includes a cross-connection queue comparison at `/queue-monitor/compare`, searchable job lists, 25-item pagination and a dedicated job detail page. Filters include status, queue, job class, creation date range and free-text search. Search covers the job name, ID, masked payload and exception text. Dashboard panels can be shown or hidden with session-based preferences. Auto-refresh can be set to 15, 30 or 60 seconds, or disabled. The dashboard adapts its available job states and actions to each driver's capabilities. Retry and delete controls are shown only when supported for the selected job state. Mutations use POST/DELETE routes, Laravel CSRF protection and the same authorization gate as dashboard access.

Worker heartbeats are stored in the `queue_monitor_workers` table. Configure the database with `workers.connection` and the stale threshold with `workers.stale_after_seconds`. Worker visibility is based on Laravel worker lifecycle events and heartbeats, identifying processes by hostname and PID. It does not claim to read Supervisor program names or Supervisor's own process state; use Supervisor XML-RPC or a separately configured integration for that level of detail.

## Supervisor worker management

The CI pipeline also starts a temporary Supervisor server and runs a live XML-RPC integration test. This verifies process listing, stopping and starting against the actual Supervisor daemon. It does not require access to your production worker hosts.

Supervisor integration is optional. It uses the Supervisor XML-RPC API. Configure each trusted server and explicitly list the process names that the dashboard may display or control:

```php
'supervisor' => [
    'servers' => [
        'app-workers' => [
            'enabled' => true,
            'url' => env('QUEUE_MONITOR_SUPERVISOR_URL'),
            'username' => env('QUEUE_MONITOR_SUPERVISOR_USER'),
            'password' => env('QUEUE_MONITOR_SUPERVISOR_PASSWORD'),
            'verify_tls' => true,
            'allow_insecure_http' => env('QUEUE_MONITOR_SUPERVISOR_ALLOW_HTTP', false),
            'timeout' => 5,
            'programs' => ['laravel-worker:*'],
        ],
    ],
],
```

Open `/queue-monitor/supervisor/app-workers` to view the configured processes. Start, stop and restart commands are available only for names matching the `programs` allowlist. Wildcards are supported. For example, `laravel-worker:*` allows all processes in that Supervisor group. Define a separate `manageQueueWorkers` gate for process control. The normal `viewQueueMonitor` gate protects the status page.

Use HTTPS and valid TLS certificates. Plain HTTP is blocked unless `allow_insecure_http` is explicitly enabled for a trusted private endpoint. Do not expose Supervisor's RPC endpoint to the public internet. Store credentials in environment variables. Process actions use POST requests and Laravel CSRF protection.

## Retry policy

Set `retry.max_attempts` to a positive value to prevent manual retry actions for jobs that have already reached that attempt count. Override the limit for specific queues with `retry.max_attempts_by_queue`, using queue names as keys. The default value `0` leaves retries unlimited. This is an operator safeguard; automatic retry timing remains governed by Laravel. Define a job-level `backoff()` method or use the worker's `--backoff` option. For example:

```php
class SendReport implements ShouldQueue
{
    public $tries = 5;

    public function backoff(): array
    {
        return [10, 30, 60];
    }
}
```

The package controls manual retry eligibility. Laravel controls automatic retry timing and worker attempts.


## Notifications

Failure and threshold alerts support Slack, Microsoft Teams, Discord, Telegram and generic JSON webhooks. Notifications are disabled by default. Add trusted webhook URLs in application configuration (never accept webhook URLs from dashboard input):

```php
'notifications' => [
    'enabled' => true,
    'timeout_seconds' => 3,
    'webhooks' => [
        env('QUEUE_MONITOR_ALERT_WEBHOOK'),
    ],
],
```

On a failed queue job, the package dispatches a `QueueAlertTriggered` event for application-specific integrations (including mail notifications) and posts a compact JSON message to each configured webhook. The `queue-monitor:check-health` command also checks backlog size, stale workers and the recent failure rate. Schedule it every minute to receive threshold alerts. Duplicate threshold alerts are suppressed for the configured cooldown period. Webhook errors are isolated from queue processing. Keep webhook URLs and Telegram bot tokens in environment variables. Protect them as secrets. Webhooks may be plain URL strings for generic JSON delivery or configuration arrays with a `driver` and `url`.

Provider-specific webhook examples:

```php
'webhooks' => [
    ['driver' => 'slack', 'url' => env('QUEUE_MONITOR_SLACK_WEBHOOK')],
    ['driver' => 'discord', 'url' => env('QUEUE_MONITOR_DISCORD_WEBHOOK')],
    [
        'driver' => 'telegram',
        'url' => env('QUEUE_MONITOR_TELEGRAM_API_URL'), // https://api.telegram.org/bot<TOKEN>/sendMessage
        'chat_id' => env('QUEUE_MONITOR_TELEGRAM_CHAT_ID'),
    ],
],
```

Slack and Teams receive a `text` message. Discord receives `content`. Telegram receives `chat_id` and `text`. Generic URL strings continue to receive the full JSON event payload.


## Design principles

- **Capability-aware:** Unsupported features are not presented as available.
- **History-aware:** Completed-job history is opt-in.
- **Secure by default:** Sensitive payload values are masked by default.
- **Laravel-native:** Uses Laravel queue events, configuration, and service container.


## More documentation

- [Health checks](docs/health-checks.md)

- [Release checklist](docs/releasing.md)

- [Queue driver capabilities](docs/drivers.md)
- [Supervisor setup and controls](docs/supervisor.md)
- [Security guidance](docs/security.md)

## Development

```bash
composer install
composer test
```

## License

MIT


## Queue SLA tracking

Queue SLA tracking measures how long jobs wait between being pushed onto a queue and being picked up by a worker. It requires persistent history and the package migrations.

Configure a default target and optional per-queue overrides:

```php
'sla' => [
    'enabled' => true,
    'default_wait_seconds' => 30,
    'queues' => [
        'critical' => 10,
        'reports' => 120,
    ],
    'window_hours' => 24,
],
```

The dashboard shows SLA compliance, measured jobs and breaches for the selected connection and queue. A job breaches its target only when its queue wait is greater than the configured limit. Pending jobs use their current wait time; started jobs use their recorded wait duration. When notifications are enabled, `queue-monitor:check-health` also reports pending jobs that exceed their queue target. Schedule the command every minute to receive these alerts.

Existing installations should run `php artisan migrate` to add enqueue-time tracking columns. SLA measurements begin as jobs are queued after the migration; older history entries are not backfilled.
