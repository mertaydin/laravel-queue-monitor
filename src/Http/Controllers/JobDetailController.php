<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;

final class JobDetailController
{
    public function __invoke(
        Request $request,
        string $connection,
        string $jobId,
        QueueDriverManager $manager
    ): View {
        $gate = (string) config('queue-monitor.authorization.gate', 'viewQueueMonitor');
        Gate::authorize($gate);

        abort_unless($manager->has($connection), 404);
        $driver = $manager->driver($connection);
        $queue = $request->query('queue');
        $queues = $driver->queues();

        abort_unless(is_string($queue) && in_array($queue, $queues, true), 404);

        $job = collect($driver->jobs($queue, null, 500))->first(
            static fn ($candidate): bool => $candidate->id === $jobId
        );

        abort_if($job === null, 404);

        return view('queue-monitor::job-detail', [
            'connection' => $connection,
            'driverName' => $driver->name(),
            'job' => $job,
            'capabilities' => $driver->capabilities()->toArray(),
            'queue' => $queue,
        ]);
    }
}
