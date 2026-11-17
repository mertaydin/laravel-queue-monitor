<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;
use MertAydin\LaravelQueueMonitor\Supervisor\SupervisorClient;
use Throwable;

final class HealthCheckController
{
    public function __invoke(QueueDriverManager $manager): View
    {
        $checks = [];
        $this->check($checks, 'Application database', function (): string {
            DB::connection()->getPdo();

            return 'The default database connection is responding.';
        });

        foreach ($manager->all() as $name => $driver) {
            $this->check($checks, 'Queue connection: '.$name, function () use ($driver): string {
                $snapshot = $driver->snapshot();
                $total = $snapshot->pending + $snapshot->running + $snapshot->failed;

                return sprintf(
                    '%s driver responded. %s pending, %s running and %s failed.',
                    ucfirst($driver->name()),
                    number_format($snapshot->pending),
                    number_format($snapshot->running),
                    number_format($snapshot->failed)
                );
            });
        }

        $workerConnection = config('queue-monitor.workers.connection');
        $workerTable = 'queue_monitor_workers';

        try {
            $exists = Schema::connection($workerConnection)->hasTable($workerTable);
            $checks[] = [
                'name' => 'Worker heartbeat storage',
                'status' => $exists ? 'healthy' : 'warning',
                'message' => $exists
                    ? 'Worker heartbeat table is available.'
                    : 'Worker heartbeat table is missing. Run the package migrations.',
            ];
        } catch (Throwable $exception) {
            $checks[] = [
                'name' => 'Worker heartbeat storage',
                'status' => 'warning',
                'message' => 'Unable to inspect worker storage: '.$exception->getMessage(),
            ];
        }

        foreach ((array) config('queue-monitor.supervisor.servers', []) as $name => $configuration) {
            if (! is_array($configuration) || ! ($configuration['enabled'] ?? false)) {
                continue;
            }

            $this->check($checks, 'Supervisor: '.$name, function () use ($configuration): string {
                $processes = (new SupervisorClient($configuration))->processes();

                return count($processes).' process(es) returned by Supervisor.';
            });
        }

        $summary = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'critical')
            ? 'critical'
            : (collect($checks)->contains(fn (array $check): bool => $check['status'] === 'warning') ? 'warning' : 'healthy');

        return view('queue-monitor::health', [
            'checks' => $checks,
            'summary' => $summary,
        ]);
    }

    private function check(array &$checks, string $name, callable $callback): void
    {
        try {
            $checks[] = [
                'name' => $name,
                'status' => 'healthy',
                'message' => $callback(),
            ];
        } catch (Throwable $exception) {
            $missingPhpRedis = $exception instanceof \Error
                && str_contains($exception->getMessage(), 'Class "Redis" not found')
                && str_contains($exception->getFile(), 'PhpRedisConnector.php');

            if ($missingPhpRedis) {
                $checks[] = [
                    'name' => $name,
                    'status' => 'warning',
                    'message' => 'The PHP Redis extension (phpredis) is not installed or enabled for this PHP runtime. Install/enable it to inspect this Redis queue connection.',
                ];

                return;
            }

            report($exception);

            $checks[] = [
                'name' => $name,
                'status' => 'critical',
                'message' => 'The check failed. Review the application log for details.',
            ];
        }
    }
}
