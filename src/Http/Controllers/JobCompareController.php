<?php

namespace MertAydin\LaravelQueueMonitor\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use MertAydin\LaravelQueueMonitor\QueueDriverManager;

final class JobCompareController
{
    public function __invoke(
        Request $request,
        string $connection,
        QueueDriverManager $manager
    ): View|RedirectResponse {
        $gate = (string) config('queue-monitor.authorization.gate', 'viewQueueMonitor');
        Gate::authorize($gate);

        abort_unless($manager->has($connection), 404);
        $validated = $request->validate([
            'queue' => ['required', 'string', 'max:191'],
            'compare_ids' => ['required', 'array', 'size:2'],
            'compare_ids.*' => ['required', 'string', 'max:191', 'distinct'],
        ]);

        $driver = $manager->driver($connection);
        $queue = $validated['queue'];
        abort_unless(in_array($queue, $driver->queues(), true), 422);

        $jobs = $driver->jobs($queue, null, 500);
        $selected = [];

        foreach ($validated['compare_ids'] as $id) {
            $job = collect($jobs)->first(static fn ($candidate): bool => $candidate->id === $id);
            abort_if($job === null, 404);
            $selected[] = $job;
        }

        $first = Arr::dot($selected[0]->payload ?? []);
        $second = Arr::dot($selected[1]->payload ?? []);
        $keys = array_values(array_unique(array_merge(array_keys($first), array_keys($second))));
        sort($keys);

        $differences = [];
        foreach ($keys as $key) {
            $firstValue = $first[$key] ?? null;
            $secondValue = $second[$key] ?? null;
            $firstExists = array_key_exists($key, $first);
            $secondExists = array_key_exists($key, $second);

            $differences[] = [
                'key' => $key,
                'first' => $firstExists ? $firstValue : null,
                'second' => $secondExists ? $secondValue : null,
                'different' => $firstExists !== $secondExists || $firstValue !== $secondValue,
            ];
        }

        return view('queue-monitor::job-compare', [
            'connection' => $connection,
            'queue' => $queue,
            'jobs' => $selected,
            'differences' => $differences,
        ]);
    }
}
