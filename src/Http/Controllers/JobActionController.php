<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use MertAydin\LaravelQueueMonitor\Contracts\QueueDriver;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;

final class JobActionController
{
    public function retry(
        Request $request,
        string $connection,
        string $jobId,
        QueueDriverManager $manager
    ): RedirectResponse {
        $driver = $this->authorizedDriver($connection, $manager);
        abort_unless($driver->capabilities()->canRetry, 403);

        $queue = $this->validatedQueue($request, $driver);
        $driver->retry($jobId, $queue);

        return redirect()
            ->route('queue-monitor.index', ['connection' => $connection, 'queue' => $queue])
            ->with('status', 'Retry requested for job '.$jobId.'.');
    }

    public function delete(
        Request $request,
        string $connection,
        string $jobId,
        QueueDriverManager $manager
    ): RedirectResponse {
        $driver = $this->authorizedDriver($connection, $manager);
        abort_unless($driver->capabilities()->canDelete, 403);

        $queue = $this->validatedQueue($request, $driver);
        $driver->delete($jobId, $queue);

        return redirect()
            ->route('queue-monitor.index', ['connection' => $connection, 'queue' => $queue])
            ->with('status', 'Job '.$jobId.' was deleted.');
    }

    private function authorizedDriver(string $connection, QueueDriverManager $manager): QueueDriver
    {
        $gate = (string) config('queue-monitor.authorization.gate', 'viewQueueMonitor');

        Gate::authorize($gate);
        abort_unless($manager->has($connection), 404);

        return $manager->driver($connection);
    }

    private function validatedQueue(Request $request, QueueDriver $driver): string
    {
        $queue = $request->input('queue');

        abort_unless(is_string($queue) && in_array($queue, $driver->queues(), true), 422);

        return $queue;
    }
}
