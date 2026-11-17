@php
    $qmAvailableLocales = config('queue-monitor.dashboard.available_locales', ['tr', 'en', 'zh_CN', 'hi', 'es', 'ar']);
    $qmDefaultLocale = config('queue-monitor.dashboard.locale', 'en');
    $qmRequestedLocale = request()->query('locale', request()->cookie('queue_monitor_locale', $qmDefaultLocale));
    $qmLocale = in_array($qmRequestedLocale, $qmAvailableLocales, true) ? $qmRequestedLocale : $qmDefaultLocale;
    $qmLanguageNames = ['tr' => 'Türkçe', 'en' => 'English', 'es' => 'Español', 'zh_CN' => '简体中文', 'hi' => 'हिन्दी', 'ar' => 'العربية'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $qmLocale) }}" dir="{{ $qmLocale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Queue Monitor</title>
    <link rel="stylesheet" href="{{ asset('vendor/queue-monitor/queue-monitor.css') }}">
    <script src="{{ asset('vendor/queue-monitor/queue-monitor.js') }}" defer></script>
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
            <a class="qm-supervisor-nav" href="{{ route('queue-monitor.compare') }}">{{ __('queue-monitor::messages.compare', [], $qmLocale) }}</a>
            <a class="qm-supervisor-nav" href="{{ route('queue-monitor.health') }}">{{ __('queue-monitor::messages.health', [], $qmLocale) }}</a>
            @foreach ($supervisorServers as $supervisorServer)
                <a class="qm-supervisor-nav" href="{{ route('queue-monitor.supervisor.index', ['server' => $supervisorServer]) }}">Supervisor · {{ $supervisorServer }}</a>
            @endforeach
            <span class="qm-live-dot"></span>
            <span>{{ __('queue-monitor::messages.overview', [], $qmLocale) }}</span>
            <span class="qm-version">PACKAGE</span>
            @include('queue-monitor::partials.language')
            @include('queue-monitor::partials.theme')
        </div>
    </header>

    <main class="qm-main">
    @php
        $visibleWidgets = session('queue-monitor.dashboard.widgets', ['analytics', 'workers', 'distribution', 'capabilities']);
        $refreshInterval = (int) session('queue-monitor.dashboard.refresh', 0);
    @endphp
        <section class="qm-heading">
            <div>
                <p class="qm-eyebrow">{{ __('queue-monitor::messages.operations_center', [], $qmLocale) }}</p>
                <h1>{{ __('queue-monitor::messages.queue_overview', [], $qmLocale) }}</h1>
                <p class="qm-subtitle">{{ __('queue-monitor::messages.subtitle', [], $qmLocale) }}</p>
            </div>
            <div class="qm-heading-actions">
                <span class="qm-updated"><span class="qm-live-dot"></span> {{ __('queue-monitor::messages.current', [], $qmLocale) }}</span>
                <a class="qm-refresh" href="{{ request()->fullUrl() }}" aria-label="{{ __('queue-monitor::messages.refresh', [], $qmLocale) }}" title="{{ __('queue-monitor::messages.refresh', [], $qmLocale) }}">↻</a>
            </div>
        </section>

        <details class="qm-panel qm-widget-preferences">
            <summary>{{ __('queue-monitor::messages.customize', [], $qmLocale) }} <span>Choose which panels are visible in this browser session</span></summary>
            <form method="POST" action="{{ route('queue-monitor.preferences') }}">
                @csrf
                @foreach (['analytics' => __('queue-monitor::messages.performance', [], $qmLocale), 'workers' => __('queue-monitor::messages.workers', [], $qmLocale), 'distribution' => __('queue-monitor::messages.distribution', [], $qmLocale), 'capabilities' => __('queue-monitor::messages.capabilities', [], $qmLocale)] as $widget => $label)
                    <label><input type="checkbox" name="widgets[]" value="{{ $widget }}" @checked(in_array($widget, $visibleWidgets, true))> {{ $label }}</label>
                @endforeach
                <label class="qm-refresh-preference"><span>{{ __('queue-monitor::messages.auto_refresh', [], $qmLocale) }}</span><select name="refresh"><option value="0" @selected($refreshInterval === 0)>Off</option><option value="15" @selected($refreshInterval === 15)>15 seconds</option><option value="30" @selected($refreshInterval === 30)>30 seconds</option><option value="60" @selected($refreshInterval === 60)>60 seconds</option></select></label>
                <button type="submit">{{ __('queue-monitor::messages.save', [], $qmLocale) }}</button>
            </form>
        </details>

        @if ($connectionCards !== [])
            <section class="qm-connections" aria-label="{{ __('queue-monitor::messages.queue', [], $qmLocale) }} connections">
                @foreach ($connectionCards as $card)
                    <a class="qm-connection-card {{ $card['connection'] === $connection ? 'is-selected' : '' }}"
                       href="{{ route('queue-monitor.index', ['connection' => $card['connection']]) }}">
                        <span class="qm-connection-icon">{{ strtoupper(substr($card['driver'], 0, 1)) }}</span>
                        <span class="qm-connection-copy">
                            <strong>{{ $card['connection'] }}</strong>
                            <small>{{ ucfirst($card['driver']) }} driver</small>
                        </span>
                        @if ($card['error'])
                            <span class="qm-connection-state is-error">{{ __('queue-monitor::messages.unavailable', [], $qmLocale) }}</span>
                        @else
                            <span class="qm-connection-state"><i></i> {{ __('queue-monitor::messages.connected', [], $qmLocale) }}</span>
                        @endif
                    </a>
                @endforeach
            </section>
        @endif

        <section class="qm-filter-panel">
            <div class="qm-filter-intro">
                <span class="qm-filter-icon">⌕</span>
                <div>
                    <strong>{{ __('queue-monitor::messages.explore', [], $qmLocale) }}</strong>
                    <small>{{ __('queue-monitor::messages.choose_connection', [], $qmLocale) }}</small>
                </div>
            </div>
            <form class="qm-filters" method="GET" action="{{ route('queue-monitor.index') }}">
                <label>
                    <span>{{ __('queue-monitor::messages.connection', [], $qmLocale) }}</span>
                    <select name="connection" data-qm-auto-submit>
                        @forelse ($connectionNames as $connectionName)
                            <option value="{{ $connectionName }}" @selected($connectionName === $connection)>{{ $connectionName }}</option>
                        @empty
                            <option value="">{{ __('queue-monitor::messages.no_connections', [], $qmLocale) }}</option>
                        @endforelse
                    </select>
                </label>
                <label>
                    <span>{{ __('queue-monitor::messages.queue', [], $qmLocale) }}</span>
                    <select name="queue">
                        @forelse ($queues as $queueName)
                            <option value="{{ $queueName }}" @selected($queueName === $queue)>{{ $queueName }}</option>
                        @empty
                            <option value="">No queues found</option>
                        @endforelse
                    </select>
                </label>
                <label>
                    <span>{{ __('queue-monitor::messages.status', [], $qmLocale) }}</span>
                    <select name="status">
                        <option value="">{{ __('queue-monitor::messages.all_states', [], $qmLocale) }}</option>
                        @foreach (['pending' => __('queue-monitor::messages.pending', [], $qmLocale), 'running' => __('queue-monitor::messages.running', [], $qmLocale), 'delayed' => __('queue-monitor::messages.delayed', [], $qmLocale), 'failed' => __('queue-monitor::messages.failed', [], $qmLocale), 'completed' => __('queue-monitor::messages.completed', [], $qmLocale)] as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="qm-search-field">
                    <span>{{ __('queue-monitor::messages.search', [], $qmLocale) }}</span>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Name, ID, payload...">
                </label>
                <label>
                    <span>Job class</span>
                    <input type="search" name="job_class" value="{{ $jobClass }}" placeholder="App\\Jobs\\...">
                </label>
                <label>
                    <span>From</span>
                    <input type="date" name="date_from" value="{{ $dateFrom }}">
                </label>
                <label>
                    <span>To</span>
                    <input type="date" name="date_to" value="{{ $dateTo }}">
                </label>
                <button class="qm-filter-button" type="submit">{{ __('queue-monitor::messages.apply', [], $qmLocale) }} <span>→</span></button>
            </form>
        </section>

        @if (session('status'))
            <div class="qm-alert qm-alert-success"><span class="qm-alert-icon">✓</span><div><strong>{{ session('status') }}</strong></div></div>
        @endif

        @if ($connectionError)
            <div class="qm-alert qm-alert-error">
                <span class="qm-alert-icon">!</span>
                <div><strong>{{ __('queue-monitor::messages.connection', [], $qmLocale) }} unavailable</strong><p>{{ __('queue-monitor::messages.connection_error', [], $qmLocale) }}</p></div>
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
                    <p class="qm-eyebrow">{{ __('queue-monitor::messages.current_workload', [], $qmLocale) }}</p>
                    <h2>{{ $queue ?? 'All queues' }} <span class="qm-driver-tag">{{ ucfirst($driverName) }}</span></h2>
                </div>
                <span class="qm-total-label">{{ number_format($totalJobs) }} {{ __('queue-monitor::messages.tracked_jobs', [], $qmLocale) }}</span>
            </section>

            <section class="qm-stats-grid">
                <article class="qm-stat-card stat-pending">
                    <div class="qm-stat-top"><span>{{ __('queue-monitor::messages.pending', [], $qmLocale) }}</span><span class="qm-stat-icon">◷</span></div>
                    <strong>{{ number_format($snapshot->pending) }}</strong>
                    <small>{{ __('queue-monitor::messages.waiting_to_process', [], $qmLocale) }}</small>
                    <div class="qm-stat-foot"><i></i> {{ __('queue-monitor::messages.ready_queue', [], $qmLocale) }}</div>
                </article>
                <article class="qm-stat-card stat-running">
                    <div class="qm-stat-top"><span>{{ __('queue-monitor::messages.running', [], $qmLocale) }}</span><span class="qm-stat-icon">↗</span></div>
                    <strong>{{ number_format($snapshot->running) }}</strong>
                    <small>{{ __('queue-monitor::messages.reserved_workers', [], $qmLocale) }}</small>
                    <div class="qm-stat-foot"><i></i> {{ __('queue-monitor::messages.in_progress', [], $qmLocale) }}</div>
                </article>
                <article class="qm-stat-card stat-completed">
                    <div class="qm-stat-top"><span>{{ __('queue-monitor::messages.completed', [], $qmLocale) }}</span><span class="qm-stat-icon">✓</span></div>
                    <strong>{{ number_format($snapshot->completed) }}</strong>
                    <small>{{ __('queue-monitor::messages.successful_jobs', [], $qmLocale) }}</small>
                    <div class="qm-stat-foot"><i></i> {{ __('queue-monitor::messages.history_dependent', [], $qmLocale) }}</div>
                </article>
                <article class="qm-stat-card stat-failed">
                    <div class="qm-stat-top"><span>Failed</span><span class="qm-stat-icon">!</span></div>
                    <strong>{{ number_format($snapshot->failed) }}</strong>
                    <small>{{ __('queue-monitor::messages.failed_jobs', [], $qmLocale) }}</small>
                    <div class="qm-stat-foot"><i></i> {{ __('queue-monitor::messages.needs_attention', [], $qmLocale) }}</div>
                </article>
            </section>

            @if ($sla['available'])
                <section class="qm-panel qm-sla-panel">
                    <div class="qm-section-heading">
                        <div><p class="qm-eyebrow">SERVICE LEVEL AGREEMENT</p><h2>{{ __('queue-monitor::messages.queue', [], $qmLocale) }} wait-time targets</h2></div>
                        <span class="qm-total-label">Last {{ $sla['window_hours'] }} hours</span>
                    </div>
                    <div class="qm-stats-grid">
                        <article class="qm-stat-card stat-completed">
                            <div class="qm-stat-top"><span>SLA compliance</span><span class="qm-stat-icon">✓</span></div>
                            <strong>{{ $sla['rate'] === null ? '—' : number_format($sla['rate'], 1).'%' }}</strong>
                            <small>Jobs processed within target</small>
                        </article>
                        <article class="qm-stat-card stat-pending">
                            <div class="qm-stat-top"><span>Jobs measured</span><span class="qm-stat-icon">◷</span></div>
                            <strong>{{ number_format($sla['total']) }}</strong>
                            <small>Jobs with recorded queue wait</small>
                        </article>
                        <article class="qm-stat-card stat-failed">
                            <div class="qm-stat-top"><span>SLA breaches</span><span class="qm-stat-icon">!</span></div>
                            <strong>{{ number_format($sla['breached']) }}</strong>
                            <small>Wait time exceeded the queue target</small>
                        </article>
                    </div>
                    @if ($sla['queues'] !== [])
                        <div class="qm-sla-queues">
                            @foreach ($sla['queues'] as $slaQueue)
                                <div class="qm-sla-queue-row">
                                    <div><strong>{{ $slaQueue['queue'] }}</strong><small>Target: {{ $slaQueue['threshold'] }} sec</small></div>
                                    <span>{{ number_format($slaQueue['within']) }} / {{ number_format($slaQueue['total']) }} within target</span>
                                    <strong>{{ number_format($slaQueue['rate'], 1) }}%</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="qm-sla-empty">No jobs with queue wait measurements were recorded in this period.</p>
                    @endif
                </section>
            @endif

            @if (in_array('analytics', $visibleWidgets, true))
        <section class="qm-analytics-grid">
            <article class="qm-panel qm-analytics-panel">
                <div class="qm-panel-heading">
                    <div><h3>{{ __('queue-monitor::messages.job_activity', [], $qmLocale) }}</h3><p>{{ __('queue-monitor::messages.completed', [], $qmLocale) }} and failed jobs from persistent history</p></div>
                    <form method="GET" action="{{ route('queue-monitor.index') }}" class="qm-period-form">
                        <input type="hidden" name="connection" value="{{ $connection }}">
                        <input type="hidden" name="queue" value="{{ $queue }}">
                        <input type="hidden" name="status" value="{{ $status }}">
                        <select name="period" aria-label="Analytics period" data-qm-auto-submit>
                            <option value="24h" @selected($period === '24h')>{{ __('queue-monitor::messages.last_24_hours', [], $qmLocale) }}</option>
                            <option value="7d" @selected($period === '7d')>{{ __('queue-monitor::messages.last_7_days', [], $qmLocale) }}</option>
                            <option value="30d" @selected($period === '30d')>{{ __('queue-monitor::messages.last_30_days', [], $qmLocale) }}</option>
                        </select>
                    </form>
                </div>
                @if ($analytics['available'])
                    <div class="qm-analytics-summary">
                        <div><strong>{{ number_format($analytics['completed_count']) }}</strong><span>{{ __('queue-monitor::messages.completed', [], $qmLocale) }}</span></div>
                        <div><strong>{{ number_format($analytics['failed_count']) }}</strong><span>Failed</span></div>
                        <div><strong>{{ $analytics['average_duration'] === null ? '—' : number_format($analytics['average_duration']).' ms' }}</strong><span>Avg. duration</span></div>
                        <div><strong>{{ $analytics['p95_duration'] === null ? '—' : number_format($analytics['p95_duration']).' ms' }}</strong><span>95th percentile</span></div>
                    </div>
                    @php $chartMax = max(1, ...$analytics['completed'], ...$analytics['failed']); @endphp
                    <div class="qm-activity-chart" role="img" aria-label="{{ __('queue-monitor::messages.completed', [], $qmLocale) }} and failed job activity">
                        @foreach ($analytics['labels'] as $index => $label)
                            <div class="qm-chart-column" title="{{ $label }}: {{ $analytics['completed'][$index] }} completed, {{ $analytics['failed'][$index] }} failed">
                                <div class="qm-chart-bars">
                                    <i class="qm-chart-completed" style="height: {{ max(2, ($analytics['completed'][$index] / $chartMax) * 100) }}%"></i>
                                    <i class="qm-chart-failed" style="height: {{ max(2, ($analytics['failed'][$index] / $chartMax) * 100) }}%"></i>
                                </div>
                                @if (count($analytics['labels']) <= 7 || $index % 3 === 0)
                                    <small>{{ $label }}</small>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="qm-chart-legend"><span><i class="qm-chart-completed"></i>{{ __('queue-monitor::messages.completed', [], $qmLocale) }}</span><span><i class="qm-chart-failed"></i>Failed</span></div>
                    @if ($analytics['top_classes'] !== [])
                        <div class="qm-slowest-jobs">
                            <strong>Slowest job classes by average duration</strong>
                            @foreach ($analytics['top_classes'] as $jobClass)
                                <div><span>{{ $jobClass['name'] }} <small>· {{ number_format($jobClass['count']) }} jobs</small></span><b>{{ number_format($jobClass['average_duration']) }} ms avg.</b></div>
                            @endforeach
                        </div>
                    @endif
                    @if ($analytics['slowest'] !== [])
                        <div class="qm-slowest-jobs">
                            <strong>Slowest completed jobs</strong>
                            @foreach ($analytics['slowest'] as $slowJob)
                                <div><span>{{ $slowJob->job_name ?: $slowJob->job_id }}</span><b>{{ number_format((int) $slowJob->duration_ms) }} ms</b></div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="qm-analytics-empty">Enable persistent history to unlock activity charts and execution-time analytics.</div>
                @endif
            </article>
            <article class="qm-panel qm-health-summary">
                <div class="qm-panel-heading"><div><h3>{{ __('queue-monitor::messages.queue', [], $qmLocale) }} health</h3><p>Based on configured thresholds</p></div></div>
                <div class="qm-health-state health-{{ $health['status'] }}"><span></span>{{ $health['label'] }}</div>
                <p>{{ $health['message'] }}</p>
                @if ($analytics['available'])
                    <div class="qm-health-metric"><span>Failed jobs in period</span><strong>{{ number_format($analytics['failed_count']) }}</strong></div>
                    <div class="qm-health-metric"><span>{{ __('queue-monitor::messages.pending', [], $qmLocale) }} now</span><strong>{{ number_format($snapshot->pending) }}</strong></div>
                @endif
            </article>
        </section>

        @endif

        @if (in_array('workers', $visibleWidgets, true) && $workerCapacity['available'])
            <section class="qm-panel qm-workers-panel">
                <div class="qm-panel-heading">
                    <div><h3>Worker capacity</h3><p>Live heartbeat counts · {{ $queue ? 'Queue: '.$queue : 'All queues' }}</p></div>
                    <span class="qm-results-count">{{ count($workers) }} tracked</span>
                </div>
                <div class="qm-worker-capacity-grid">
                    <article class="qm-capacity-card capacity-active"><span>Active workers</span><strong>{{ number_format($workerCapacity['active']) }}</strong><small>{{ number_format($workerCapacity['active_for_queue']) }} serving selected queue</small></article>
                    <article class="qm-capacity-card capacity-stale"><span>Stale workers</span><strong>{{ number_format($workerCapacity['stale']) }}</strong><small>No heartbeat within configured threshold</small></article>
                    <article class="qm-capacity-card capacity-stopped"><span>Stopped workers</span><strong>{{ number_format($workerCapacity['stopped']) }}</strong><small>Reported a graceful shutdown</small></article>
                    <article class="qm-capacity-card capacity-load"><span>{{ __('queue-monitor::messages.pending', [], $qmLocale) }} jobs / active worker</span><strong>{{ $workerCapacity['pending_per_active_worker'] === null ? '—' : number_format($workerCapacity['pending_per_active_worker'], 1) }}</strong><small>{{ $workerCapacity['pending'] === null ? 'Queue depth unavailable' : number_format($workerCapacity['pending']).' pending jobs in selected queue' }}</small></article>
                </div>
                @if ($workerCapacity['status'] === 'attention')
                    <div class="qm-worker-capacity-note is-warning">Jobs are waiting, but no active worker heartbeat is available for this queue.</div>
                @elseif ($workerCapacity['active_for_queue'] > 0)
                    <div class="qm-worker-capacity-note">{{ __('queue-monitor::messages.pending', [], $qmLocale) }} jobs per worker is calculated from the selected queue depth and its active worker heartbeats.</div>
                @else
                    <div class="qm-worker-capacity-note">No active worker heartbeat is currently recorded for the selected queue.</div>
                @endif
                @if ($workers !== [])
                    <div class="qm-workers-grid">
                        @foreach ($workers as $worker)
                            <article class="qm-worker-card">
                                <div class="qm-worker-top"><strong>{{ $worker->hostname }}:{{ $worker->process_id }}</strong><span class="qm-worker-state worker-{{ $worker->display_status }}"><i></i>{{ ucfirst($worker->display_status) }}</span></div>
                                <div class="qm-worker-meta"><span>{{ $worker->connection }}</span><span>{{ __('queue-monitor::messages.queue', [], $qmLocale) }}: {{ $worker->queue ?: '—' }}</span></div>
                                <small>Last heartbeat: {{ $worker->heartbeat_at ? \Illuminate\Support\Carbon::parse($worker->heartbeat_at)->diffForHumans() : 'Unknown' }}</small>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="qm-worker-capacity-note">No worker heartbeat records have been collected for this connection yet.</div>
                @endif
            </section>
        @endif

        @if (in_array('distribution', $visibleWidgets, true) || in_array('capabilities', $visibleWidgets, true))
        <section class="qm-lower-grid">
                @if (in_array('distribution', $visibleWidgets, true))
                <article class="qm-panel qm-distribution">
                    <div class="qm-panel-heading">
                        <div><h3>{{ __('queue-monitor::messages.queue', [], $qmLocale) }} distribution</h3><p>How the current workload is split</p></div>
                        <span class="qm-panel-menu">•••</span>
                    </div>
                    <div class="qm-distribution-total"><strong>{{ number_format($snapshot->pending + $snapshot->running + $snapshot->completed + $snapshot->failed + $delayed) }}</strong><span>Total jobs</span></div>
                    <div class="qm-bar" role="img" aria-label="{{ __('queue-monitor::messages.queue', [], $qmLocale) }} state distribution">
                        <span class="bar-pending" style="width: {{ $pendingWidth }}%"></span>
                        <span class="bar-running" style="width: {{ $runningWidth }}%"></span>
                        <span class="bar-completed" style="width: {{ $completedWidth }}%"></span>
                        <span class="bar-failed" style="width: {{ $failedWidth }}%"></span>
                        <span class="bar-delayed" style="width: {{ $delayedWidth }}%"></span>
                    </div>
                    <div class="qm-legend">
                        <span><i class="legend-pending"></i>{{ __('queue-monitor::messages.pending', [], $qmLocale) }} <b>{{ number_format($snapshot->pending) }}</b></span>
                        <span><i class="legend-running"></i>{{ __('queue-monitor::messages.running', [], $qmLocale) }} <b>{{ number_format($snapshot->running) }}</b></span>
                        <span><i class="legend-completed"></i>{{ __('queue-monitor::messages.completed', [], $qmLocale) }} <b>{{ number_format($snapshot->completed) }}</b></span>
                        <span><i class="legend-failed"></i>Failed <b>{{ number_format($snapshot->failed) }}</b></span>
                        <span><i class="legend-delayed"></i>{{ __('queue-monitor::messages.delayed', [], $qmLocale) }} <b>{{ number_format($delayed) }}</b></span>
                    </div>
                </article>
                @endif
                @if (in_array('capabilities', $visibleWidgets, true))
                <article class="qm-panel qm-health">
                    <div class="qm-panel-heading">
                        <div><h3>{{ __('queue-monitor::messages.connection', [], $qmLocale) }} capabilities</h3><p>Available actions for this driver</p></div>
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
                @endif
            </section>
            @endif

            @if ($driverName === 'beanstalkd')
                <div class="qm-info-note"><strong>Beanstalkd visibility:</strong> reserved job counts are available, but the Beanstalkd protocol does not expose reserved job payloads for listing. Only the next ready, delayed and buried job can be inspected per tube.</div>
            @endif

            <section class="qm-panel qm-jobs-panel">
                <div class="qm-panel-heading qm-jobs-heading">
                    <div><h3>Recent jobs</h3><p>Inspect payloads and execution details for {{ $queue ?? 'this connection' }}</p></div>
                    <span class="qm-results-count">{{ $jobs->total() }} results <span>·</span> {{ $jobs->perPage() }} per page</span>
                </div>
                @if ($jobs === [])
                    <div class="qm-empty">
                        <span class="qm-empty-icon">⌁</span>
                        <strong>No jobs to display</strong>
                        <p>There are no jobs matching the selected queue and filters, or this driver cannot expose that state.</p>
                    </div>
                @else
                    <form id="qm-compare-form" method="POST" action="{{ route('queue-monitor.jobs.compare', ['connection' => $connection]) }}" class="qm-compare-toolbar">
                        @csrf
                        <input type="hidden" name="queue" value="{{ $queue }}">
                        <span>{{ __('queue-monitor::messages.payload', [], $qmLocale) }} comparison</span>
                        <button type="submit" onclick="return confirm('Compare the two selected job payloads?')">Compare selected (choose 2)</button>
                    </form>
                    <form id="qm-bulk-form" method="POST" action="{{ route('queue-monitor.jobs.bulk', ['connection' => $connection]) }}" class="qm-bulk-toolbar">
                        @csrf
                        <input type="hidden" name="queue" value="{{ $queue }}">
                        <label><span>Selected jobs</span><select name="action" required><option value="retry">Retry selected</option><option value="delete">Delete selected</option></select></label>
                        <button type="submit" onclick="return confirm('Apply this action to the selected jobs?')">Run bulk action</button>
                        <small>Up to 100 jobs per operation</small>
                    </form>
                    <div class="qm-table-wrap">
                        <table class="qm-table">
                            <thead><tr><th>Compare</th><th>Select</th><th>Job</th><th>{{ __('queue-monitor::messages.status', [], $qmLocale) }}</th><th>Attempts</th><th>Created</th><th>Details</th><th>Actions</th></tr></thead>
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
                                    <td><input class="qm-job-select" type="checkbox" name="compare_ids[]" value="{{ $job->id }}" form="qm-compare-form" aria-label="Compare {{ $job->name ?: $job->id }}"></td>
                                    <td><input class="qm-job-select" type="checkbox" name="job_ids[]" value="{{ $job->id }}" form="qm-bulk-form" aria-label="Select {{ $job->name ?: $job->id }}"></td>
                                    <td>
                                        <a class="qm-job-name" href="{{ route('queue-monitor.jobs.show', ['connection' => $connection, 'jobId' => $job->id, 'queue' => $queue]) }}">{{ $job->name ?: 'Unnamed job' }}</a>
                                        <code>{{ \Illuminate\Support\Str::limit($job->id, 22) }}</code>
                                    </td>
                                    <td><span class="qm-status status-{{ $jobStatusClass }}"><i></i>{{ ucfirst($job->status) }}</span></td>
                                    <td>{{ $job->attempts ?? '—' }}</td>
                                    <td>{{ $job->createdAt ? date('d M Y, H:i', $job->createdAt) : '—' }}</td>
                                    <td>
                                        @if ($job->payload !== null)
                                            <details class="qm-payload">
                                                <summary>{{ __('queue-monitor::messages.view_payload', [], $qmLocale) }} <span>↗</span></summary>
                                                <pre>{{ json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                            </details>
                                        @elseif ($job->exception)
                                            <details class="qm-payload">
                                                <summary>{{ __('queue-monitor::messages.view_exception', [], $qmLocale) }} <span>↗</span></summary>
                                                <pre>{{ $job->exception }}</pre>
                                            </details>
                                        @else
                                            <span class="qm-muted">{{ __('queue-monitor::messages.no_details', [], $qmLocale) }}</span>
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
                    @if ($jobs->hasPages())
                        <nav class="qm-pagination" aria-label="Job pages">
                            <span>Page {{ $jobs->currentPage() }} of {{ $jobs->lastPage() }}</span>
                            <div>
                                @if ($jobs->onFirstPage())
                                    <span class="is-disabled">Previous</span>
                                @else
                                    <a href="{{ $jobs->previousPageUrl() }}">Previous</a>
                                @endif
                                @if ($jobs->hasMorePages())
                                    <a href="{{ $jobs->nextPageUrl() }}">Next</a>
                                @else
                                    <span class="is-disabled">Next</span>
                                @endif
                            </div>
                        </nav>
                    @endif
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
@if ($refreshInterval > 0)
    <div class="qm-auto-refresh" data-qm-refresh-ms="{{ $refreshInterval * 1000 }}" hidden></div>
@endif
</body>
</html>
