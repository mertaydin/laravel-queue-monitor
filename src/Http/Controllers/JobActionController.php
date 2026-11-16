<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use MertAydin\LaravelQueueMonitor\Contracts\QueueDriver;
use MertAydin\LaravelQueueMonitor\Data\QueueJob;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;
use Throwable;

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
        $maxAttempts = max(0, (int) config('queue-monitor.retry.max_attempts', 0));

        if ($maxAttempts > 0) {
            $job = collect($driver->jobs($queue, null, 500))
                ->first(static fn (QueueJob $candidate): bool => $candidate->id === $jobId);

            abort_if(
                $job !== null && $job->attempts !== null && $job->attempts >= $maxAttempts,
                422,
                sprintf('This job reached the configured retry limit of %d attempts.', $maxAttempts)
            );
        }

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

    public function bulk(
        Request $request,
        string $connection,
        QueueDriverManager $manager
    ): RedirectResponse {
        $driver = $this->authorizedDriver($connection, $manager);
        $validated = $request->validate([
            'action' => ['required', 'in:retry,delete'],
            'job_ids' => ['required', 'array', 'min:1', 'max:100'],
            'job_ids.*' => ['required', 'string', 'max:191'],
            'queue' => ['required', 'string', 'max:191'],
        ]);

        $queue = $this->validatedQueue($request, $driver);
        $action = $validated['action'];

        abort_if($action === 'retry' && ! $driver->capabilities()->canRetry, 403);
        abort_if($action === 'delete' && ! $driver->capabilities()->canDelete, 403);

        $succeeded = 0;
        $failed = 0;

        foreach (array_unique($validated['job_ids']) as $jobId) {
            try {
                $driver->{$action}($jobId, $queue);
                $succeeded++;
            } catch (Throwable) {
                $failed++;
            }
        }

        $message = sprintf('%d job(s) processed successfully; %d failed.', $succeeded, $failed);

        return redirect()
            ->route('queue-monitor.index', ['connection' => $connection, 'queue' => $queue])
            ->with('status', $message);
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
