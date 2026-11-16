# Health checks

Open `/queue-monitor/health` to run read-only diagnostics.

The page checks:

- The application's default database connection.
- Every queue connection registered by the package.
- The worker heartbeat table.
- Each enabled Supervisor server configured in `queue-monitor.supervisor.servers`.

Checks run when the page is opened or refreshed. They are not a background monitor and do not send alerts. Supervisor checks make a live XML-RPC request, so keep the configured endpoint private and use a short timeout.

The health page uses the same `authorization.gate` as the dashboard. It does not start, stop or restart workers and does not modify jobs.


## Scheduled threshold alerts

Enable notifications and configure trusted webhook URLs. Then schedule the command in the host application's `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('queue-monitor:check-health')->everyMinute();
```

The command checks queue backlog, stale worker heartbeats and the recent job failure rate. It sends alerts through the same event and webhook dispatcher used for failed jobs. Repeated alerts for the same condition are suppressed for `notifications.cooldown_minutes`.

The scheduler must be running in the host application. The command does not start a scheduler or daemon itself.
