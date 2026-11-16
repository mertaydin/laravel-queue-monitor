<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use MertAydin\LaravelQueueMonitor\Supervisor\SupervisorClient;
use Throwable;

final class SupervisorController
{
    public function index(string $server): View
    {
        Gate::authorize((string) config('queue-monitor.authorization.gate', 'viewQueueMonitor'));

        $configuration = $this->serverConfiguration($server);
        $allowedProcesses = array_values(array_filter((array) ($configuration['programs'] ?? []), 'is_string'));
        $processes = [];
        $error = null;

        try {
            $client = new SupervisorClient($configuration);
            $processes = array_values(array_filter(
                $client->processes(),
                static fn (array $process): bool => collect($allowedProcesses)->contains(static fn (string $allowed): bool => fnmatch($allowed, (string) ($process['name'] ?? '')))
            ));
        } catch (Throwable $exception) {
            report($exception);\n            $error = 'Unable to reach Supervisor. Check the endpoint, credentials and RPC settings.';
        }

        return view('queue-monitor::supervisor', [
            'server' => $server,
            'processes' => $processes,
            'error' => $error,
        ]);
    }

    public function action(string $server, string $process, string $action): RedirectResponse
    {
        Gate::authorize((string) config('queue-monitor.authorization.manage_gate', 'manageQueueWorkers'));

        $configuration = $this->serverConfiguration($server);
        abort_unless(collect((array) ($configuration['programs'] ?? []))->contains(static fn (string $allowed): bool => fnmatch($allowed, $process)), 404);
        abort_unless(in_array($action, ['start', 'stop', 'restart'], true), 404);

        $client = new SupervisorClient($configuration);

        match ($action) {
            'start' => $client->start($process),
            'stop' => $client->stop($process),
            'restart' => $this->restart($client, $process),
        };

        return redirect()
            ->route('queue-monitor.supervisor.index', ['server' => $server])
            ->with('status', sprintf('Supervisor process [%s] %s request completed.', $process, $action));
    }

    private function restart(SupervisorClient $client, string $process): void
    {
        $client->stop($process);
        $client->start($process);
    }

    private function serverConfiguration(string $server): array
    {
        $configuration = config('queue-monitor.supervisor.servers.'.$server);

        abort_unless(is_array($configuration) && ($configuration['enabled'] ?? false), 404);

        return $configuration;
    }
}
