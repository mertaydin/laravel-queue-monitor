<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Queue Monitor</title>
    <link rel="stylesheet" href="{{ asset('vendor/queue-monitor/queue-monitor.css') }}">
</head>
<body>
<div class="qm-shell">
    <header class="qm-topbar">
        <a class="qm-brand" href="{{ route('queue-monitor.index') }}">
            <span class="qm-brand-mark"><span></span><span></span><span></span></span>
            <span>
                <strong>Queue Monitor</strong>
                <small>Laravel queue operations</small>
            </span>
        </a>
        <div class="qm-topbar-meta">
            <span class="qm-live-dot"></span>
            <span>Monitoring overview</span>
            <span class="qm-version">PACKAGE</span>
        </div>
    </header>

    <main class="qm-main">
        <section class="qm-heading">
            <div>
                <p class="qm-eyebrow">OPERATIONS CENTER</p>
                <h1>Queue overview</h1>
                <p class="qm-subtitle">Monitor workload, inspect jobs and spot issues across your queue connections.</p>
            </div>
            <div class="qm-heading-actions">
                <span class="qm-updated"><span class="qm-live-dot"></span> Current snapshot</span>
                <a class="qm-refresh" href="{{ request()->fullUrl() }}" aria-label="Refresh dashboard" title="Refresh dashboard">↻</a>
            </div>
        </section>

        @if ($connectionCards !== [])
            <section class="qm-connections" aria-label="Queue connections">
                @foreach ($connectionCards as $card)
                    <a class="qm-connection-card {{ $card['connection'] === $connection ? 'is-selected' : '' }}"
                       href="{{ route('queue-monitor.index', ['connection' => $card['connection']]) }}">
                        <span class="qm-connection-icon">{{ strtoupper(substr($card['driver'], 0, 1)) }}</span>
                        <span class="qm-connection-copy">
                            <strong>{{ $card['connection'] }}</strong>
                            <small>{{ ucfirst($card['driver']) }} driver</small>
                        </span>
                        @if ($card['error'])
                            <span class="qm-connection-state is-error">Unavailable</span>
                        @else
                            <span class="qm-connection-state"><i></i> Connected</span>
                        @endif
                    </a>
                @endforeach
            </section>
        @endif

        <section class="qm-filter-panel">
            <div class="qm-filter-intro">
                <span class="qm-filter-icon">⌕</span>
                <div>
                    <strong>Explore jobs</strong>
                    <small>Choose a connection, queue and job state</small>
                </div>
            </div>
            <form class="qm-filters" method="GET" action="{{ route('queue-monitor.index') }}">
                <label>
                    <span>Connection</span>
                    <select name="connection" onchange="this.form.submit()">
                        @forelse ($connectionNames as $connectionName)
                            <option value="{{ $connectionName }}" @selected($connectionName === $connection)>{{ $connectionName }}</option>
                        @empty
                            <option value="">No connections</option>
                        @endforelse
                    </select>
                </label>
                <label>
                    <span>Queue</span>
                    <select name="queue">
                        @forelse ($queues as $queueName)
                            <option value="{{ $queueName }}" @selected($queueName === $queue)>{{ $queueName }}</option>
                        @empty
                            <option value="">No queues found</option>
                        @endforelse
                    </select>
                </label>
                <label>
                    <span>Status</span>
                    <select name="status">
                        <option value="">All states</option>
                        @foreach (['pending' => 'Pending', 'running' => 'Running', 'delayed' => 'Delayed', 'failed' => 'Failed / buried', 'completed' => 'Completed'] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="qm-filter-button" type="submit">Apply filters <span>→</span></button>
            </form>
        </section>

        @if (session('status'))
            <div class="qm-alert qm-alert-success"><span class="qm-alert-icon">✓</span><div><strong>{{ session('status') }}</strong></div></div>
        @endif

        @if ($connectionError)
            <div class="qm-alert qm-alert-error">
                <span class="qm-alert-icon">!</span>
                <div><strong>Connection unavailable</strong><p>The selected queue connection could not be queried. Check its configuration and service health.</p></div>
            </div>
        @elseif ($snapshot !== null)
            @php
                $delayed = (int) ($snapshot->metadata['delayed'] ?? 0);
                $totalJobs = $snapshot->pending + $snapshot->running + $snapshot->completed + $snapshot->failed + $delayed;
                $total = max(1, $totalJobs);
                $pendingWidth = round(($snapshot->pending / $total) * 100, 1);
                $runningWidth = round(($snapshot->running / $total) * 100, 1);
                $completedWidth = round(($snapshot->completed / $total) * 100, 1);
                $failedWidth = round(($snapshot->failed / $total) * 100, 1);
                $delayedWidth = round(($delayed / $total) * 100, 1);
            @endphp

            <section class="qm-section-heading">
                <div>
                    <p class="qm-eyebrow">CURRENT WORKLOAD</p>
                    <h2>{{ $queue ?? 'All queues' }} <span class="qm-driver-tag">{{ ucfirst($driverName) }}</span></h2>
                </div>
                <span class="qm-total-label">{{ number_format($totalJobs) }} tracked jobs</span>
            </section>

            <section class="qm-stats-grid">
                <article class="qm-stat-card stat-pending">
                    <div class="qm-stat-top"><span>Pending</span><span class="qm-stat-icon">◷</span></div>
                    <strong>{{ number_format($snapshot->pending) }}</strong>
                    <small>Waiting to be processed</small>
                    <div class="qm-stat-foot"><i></i> Ready in queue</div>
                </article>
                <article class="qm-stat-card stat-running">
                    <div class="qm-stat-top"><span>Running</span><span class="qm-stat-icon">↗</span></div>
                    <strong>{{ number_format($snapshot->running) }}</strong>
                    <small>Currently reserved by workers</small>
                    <div class="qm-stat-foot"><i></i> In progress</div>
                </article>
                <article class="qm-stat-card stat-completed">
                    <div class="qm-stat-top"><span>Completed</span><span class="qm-stat-icon">✓</span></div>
                    <strong>{{ number_format($snapshot->completed) }}</strong>
                    <small>Recorded successful jobs</small>
                    <div class="qm-stat-foot"><i></i> History dependent</div>
                </article>
                <article class="qm-stat-card stat-failed">
                    <div class="qm-stat-top"><span>Failed</span><span class="qm-stat-icon">!</span></div>
                    <strong>{{ number_format($snapshot->failed) }}</strong>
                    <small>Failed or buried jobs</small>
                    <div class="qm-stat-foot"><i></i> Needs attention</div>
                </article>
            </section>

            <section class="qm-lower-grid">
                <article class="qm-panel qm-distribution">
                    <div class="qm-panel-heading">
                        <div><h3>Queue distribution</h3><p>How the current workload is split</p></div>
                        <span class="qm-panel-menu">•••</span>
                    </div>
                    <div class="qm-distribution-total"><strong>{{ number_format($snapshot->pending + $snapshot->running + $snapshot->completed + $snapshot->failed + $delayed) }}</strong><span>Total jobs</span></div>
                    <div class="qm-bar" role="img" aria-label="Queue state distribution">
                        <span class="bar-pending" style="width: {{ $pendingWidth }}%"></span>
                        <span class="bar-running" style="width: {{ $runningWidth }}%"></span>
                        <span class="bar-completed" style="width: {{ $completedWidth }}%"></span>
                        <span class="bar-failed" style="width: {{ $failedWidth }}%"></span>
                        <span class="bar-delayed" style="width: {{ $delayedWidth }}%"></span>
                    </div>
                    <div class="qm-legend">
                        <span><i class="legend-pending"></i>Pending <b>{{ number_format($snapshot->pending) }}</b></span>
                        <span><i class="legend-running"></i>Running <b>{{ number_format($snapshot->running) }}</b></span>
                        <span><i class="legend-completed"></i>Completed <b>{{ number_format($snapshot->completed) }}</b></span>
                        <span><i class="legend-failed"></i>Failed <b>{{ number_format($snapshot->failed) }}</b></span>
                        <span><i class="legend-delayed"></i>Delayed <b>{{ number_format($delayed) }}</b></span>
                    </div>
                </article>
                <article class="qm-panel qm-health">
                    <div class="qm-panel-heading">
                        <div><h3>Connection capabilities</h3><p>Available actions for this driver</p></div>
                        <span class="qm-capability-count">{{ count(array_filter($capabilities)) }}/{{ count($capabilities) }}</span>
                    </div>
                    <div class="qm-capability-list">
                        @foreach ([
                            'can_list_queues' => 'List queues',
                            'can_inspect_pending' => 'Inspect pending jobs',
                            'can_inspect_running' => 'Inspect running jobs',
                            'can_inspect_completed' => 'Inspect completed jobs',
                            'can_inspect_failed' => 'Inspect failed jobs',
                            'can_retry' => 'Retry jobs',
                            'can_delete' => 'Delete jobs',
                            'has_persistent_history' => 'Persistent history',
                        ] as $key => $label)
                            <div><span>{{ $label }}</span><b class="{{ ($capabilities[$key] ?? false) ? 'is-enabled' : 'is-disabled' }}">{{ ($capabilities[$key] ?? false) ? 'Available' : 'Not available' }}</b></div>
                        @endforeach
                    </div>
                </article>
            </section>

            @if ($driverName === 'beanstalkd')
                <div class="qm-info-note"><strong>Beanstalkd visibility:</strong> reserved job counts are available, but the Beanstalkd protocol does not expose reserved job payloads for listing. Only the next ready, delayed and buried job can be inspected per tube.</div>
            @endif

            <section class="qm-panel qm-jobs-panel">
                <div class="qm-panel-heading qm-jobs-heading">
                    <div><h3>Recent jobs</h3><p>Inspect payloads and execution details for {{ $queue ?? 'this connection' }}</p></div>
                    <span class="qm-results-count">{{ count($jobs) }} results <span>·</span> max 50</span>
                </div>
                @if ($jobs === [])
                    <div class="qm-empty">
                        <span class="qm-empty-icon">⌁</span>
                        <strong>No jobs to display</strong>
                        <p>There are no jobs matching the selected queue and filters, or this driver cannot expose that state.</p>
                    </div>
                @else
                    <div class="qm-table-wrap">
                        <table class="qm-table">
                            <thead><tr><th>Job</th><th>Status</th><th>Attempts</th><th>Created</th><th>Details</th><th>Actions</th></tr></thead>
                            <tbody>
                            @foreach ($jobs as $job)
                                @php
                                    $jobStatusClass = match ($job->status) {
                                        'pending' => 'pending',
                                        'running' => 'running',
                                        'completed' => 'completed',
                                        'failed' => 'failed',
                                        'delayed' => 'delayed',
                                        default => 'neutral',
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div class="qm-job-name">{{ $job->name ?: 'Unnamed job' }}</div>
                                        <code>{{ \Illuminate\Support\Str::limit($job->id, 22) }}</code>
                                    </td>
                                    <td><span class="qm-status status-{{ $jobStatusClass }}"><i></i>{{ ucfirst($job->status) }}</span></td>
                                    <td>{{ $job->attempts ?? '—' }}</td>
                                    <td>{{ $job->createdAt ? date('d M Y, H:i', $job->createdAt) : '—' }}</td>
                                    <td>
                                        @if ($job->payload !== null)
                                            <details class="qm-payload">
                                                <summary>View payload <span>↗</span></summary>
                                                <pre>{{ json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </details>
                                        @elseif ($job->exception)
                                            <details class="qm-payload">
                                                <summary>View exception <span>↗</span></summary>
                                                <pre>{{ $job->exception }}</pre>
                                            </details>
                                        @else
                                            <span class="qm-muted">No details</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="qm-job-actions">
                                            @if (($capabilities['can_retry'] ?? false) && in_array($job->status, ['failed', 'delayed', 'running'], true))
                                                <form method="POST" action="{{ route('queue-monitor.jobs.retry', ['connection' => $connection, 'jobId' => $job->id]) }}">
                                                    @csrf
                                                    <input type="hidden" name="queue" value="{{ $queue }}">
                                                    <button class="qm-action-button action-retry" type="submit">Retry</button>
                                                </form>
                                            @endif
                                            @if (($capabilities['can_delete'] ?? false) && ($job->status !== 'completed' || $driverName === 'database'))
                                                <form method="POST" action="{{ route('queue-monitor.jobs.delete', ['connection' => $connection, 'jobId' => $job->id]) }}" onsubmit="return confirm('Delete this job?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="queue" value="{{ $queue }}">
                                                    <button class="qm-action-button action-delete" type="submit">Delete</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @else
            <section class="qm-panel qm-empty-state">
                <span class="qm-empty-icon">⌁</span>
                <h2>No queue connections registered</h2>
                <p>Configure a supported Laravel queue connection using the database, Redis or Beanstalkd driver to start monitoring.</p>
            </section>
        @endif

        <footer class="qm-footer"><span>Laravel Queue Monitor</span><span>Secure queue visibility for your application</span></footer>
    </main>
</div>
</body>
</html>
