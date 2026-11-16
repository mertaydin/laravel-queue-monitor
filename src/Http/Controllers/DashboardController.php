<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;
use Throwable;

final class DashboardController
{
    public function __invoke(Request $request, QueueDriverManager $manager): View
    {
        $gate = (string) config('queue-monitor.authorization.gate', 'viewQueueMonitor');

        abort_unless(Gate::has($gate), 403);
        Gate::authorize($gate);

        $drivers = $manager->all();
        $connectionNames = array_keys($drivers);
        $requestedConnection = $request->query('connection');
        $connection = is_string($requestedConnection) && isset($drivers[$requestedConnection])
            ? $requestedConnection
            : ($connectionNames[0] ?? null);
        $driver = $connection === null ? null : $drivers[$connection];
        $queues = [];
        $queue = null;
        $status = $request->query('status');
        $allowedStatuses = ['pending', 'running', 'delayed', 'failed', 'completed'];

        if (is_string($status) === false || in_array($status, $allowedStatuses, true) === false) {
            $status = null;
        }

        $snapshot = null;
        $jobs = [];
        $connectionError = false;

        if ($driver !== null) {
            try {
                $queues = $driver->queues();
                $requestedQueue = $request->query('queue');
                $queue = is_string($requestedQueue) && in_array($requestedQueue, $queues, true)
                    ? $requestedQueue
                    : ($queues[0] ?? null);

                $snapshot = $driver->snapshot($queue);

                if ($queue !== null) {
                    $jobs = $driver->jobs($queue, $status, 50);
                }
            } catch (Throwable) {
                $connectionError = true;
            }
        }

        $capabilities = [];

        if ($driver !== null && $connectionError === false) {
            try {
                $capabilities = $driver->capabilities()->toArray();
            } catch (Throwable) {
                $connectionError = true;
            }
        }

        $connectionCards = [];

        foreach ($drivers as $name => $registeredDriver) {
            try {
                $connectionCards[] = [
                    'connection' => $name,
                    'driver' => $registeredDriver->name(),
                    'snapshot' => $registeredDriver->snapshot(),
                    'error' => false,
                ];
            } catch (Throwable) {
                $connectionCards[] = [
                    'connection' => $name,
                    'driver' => $registeredDriver->name(),
                    'snapshot' => null,
                    'error' => true,
                ];
            }
        }

        return view('queue-monitor::dashboard', [
            'connection' => $connection,
            'connectionCards' => $connectionCards,
            'connectionError' => $connectionError,
            'connectionNames' => $connectionNames,
            'driverName' => $driver?->name(),
            'jobs' => $jobs,
            'queues' => $queues,
            'queue' => $queue,
            'snapshot' => $snapshot,
            'status' => $status,
            'capabilities' => $capabilities,
        ]);
    }
}
