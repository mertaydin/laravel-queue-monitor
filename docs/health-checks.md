# Health checks

Open `/queue-monitor/health` to run read-only diagnostics.

The page checks:

- The application's default database connection.
- Every queue connection registered by the package.
- The worker heartbeat table.
- Each enabled Supervisor server configured in `queue-monitor.supervisor.servers`.

Checks run when the page is opened or refreshed. They are not a background monitor and do not send alerts. Supervisor checks make a live XML-RPC request, so keep the configured endpoint private and use a short timeout.

The health page uses the same `authorization.gate` as the dashboard. It does not start, stop or restart workers and does not modify jobs.
