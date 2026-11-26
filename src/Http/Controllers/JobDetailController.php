<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;
use Throwable;

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

        $status = $request->query('status');
        $supportedStatuses = ['pending', 'running', 'delayed', 'failed', 'completed'];

        abort_unless($status === null || (is_string($status) && in_array($status, $supportedStatuses, true)), 422);

        $job = collect($driver->jobs($queue, $status, 500))->first(
            static fn ($candidate): bool => $candidate->id === $jobId
        );

        abort_if($job === null, 404);

        $workflow = ['batch_id' => null, 'chain_remaining' => 0];

        if ((bool) config('queue-monitor.history.enabled', false)) {
            $historyConnection = config('queue-monitor.history.connection');
            $historyTable = config('queue-monitor.history.table', 'queue_monitor_jobs');

            try {
                $schema = Schema::connection($historyConnection);

                if ($schema->hasTable($historyTable)
                    && $schema->hasColumn($historyTable, 'batch_id')
                    && $schema->hasColumn($historyTable, 'chain_remaining')) {
                    $record = DB::connection($historyConnection)->table($historyTable)
                        ->where('connection', $connection)
                        ->where('job_id', $jobId)
                        ->first(['batch_id', 'chain_remaining']);

                    if ($record !== null) {
                        $workflow = [
                            'batch_id' => $record->batch_id,
                            'chain_remaining' => (int) $record->chain_remaining,
                        ];
                    }
                }
            } catch (Throwable) {
                // Workflow metadata is optional; keep job inspection available if history is unavailable.
            }
        }

        return view('queue-monitor::job-detail', [
            'connection' => $connection,
            'driverName' => $driver->name(),
            'job' => $job,
            'capabilities' => $driver->capabilities()->toArray(),
            'queue' => $queue,
            'workflow' => $workflow,
        ]);
    }
}
