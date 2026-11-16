# Supervisor integration

The Supervisor integration reads process status. It can also start, stop and restart configured programs.

## 1. Configure Supervisor

Enable Supervisor's XML-RPC HTTP server. Keep it on a private interface.

Example:

```ini
[inet_http_server]
port=127.0.0.1:9001
username=queue-monitor
password=use-a-long-secret
```

The RPC endpoint is usually `http://127.0.0.1:9001/RPC2`. Use HTTPS when the endpoint is accessed over a network. Do not expose the Supervisor endpoint to the public internet.

## 2. Configure the package

Add a server to `config/queue-monitor.php`:

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

Add credentials to the application's environment. Do not commit secrets.

The `programs` list is an allowlist. Wildcards use `fnmatch` syntax. Only matching processes appear in the dashboard. Only matching processes can be controlled.

Plain HTTP is blocked by default. Set `allow_insecure_http` to `true` only for a trusted private endpoint. Keep TLS verification enabled.

## 3. Set permissions

The status page uses the `viewQueueMonitor` gate by default.

Process actions use a separate `manageQueueWorkers` gate. Define it in your application:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('manageQueueWorkers', fn ($user) => $user !== null && $user->is_admin);
```

Grant this permission only to trusted operators.

## 4. Open the page

Visit:

```text
/queue-monitor/supervisor/app-workers
```

The page shows process state, PID and Supervisor's process description. It provides start, stop and restart actions.

Restart uses a stop command followed by a start command. It is not an atomic operation. Check the process state after the action.

## Troubleshooting

- Check that the RPC endpoint is reachable from the Laravel application server.
- Check the username and password.
- Check the endpoint path. It should usually end with `/RPC2`.
- Check that the process name matches the configured allowlist.
- Check Laravel logs for connection errors.
