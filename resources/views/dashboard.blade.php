@php
    $qmDisplayValue = static function ($value): string {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    };
    $qmAvailableLocales = config('queue-monitor.dashboard.available_locales', ['tr', 'en', 'zh_CN', 'hi', 'es', 'ar']);
    $qmDefaultLocale = config('queue-monitor.dashboard.locale', 'en');
    $qmRequestedLocale = request()->query('locale', request()->cookie('queue_monitor_locale', $qmDefaultLocale));
    $qmLocale = in_array($qmRequestedLocale, $qmAvailableLocales, true) ? $qmRequestedLocale : $qmDefaultLocale;
    $qmLanguageNames = ['tr' => 'Türkçe', 'en' => 'English', 'es' => 'Español', 'zh_CN' => '简体中文', 'hi' => 'हिन्दी', 'ar' => 'العربية'];
@endphp
<!DOCTYPE html>
<html lang="{{ $qmDisplayValue(str_replace('_', '-', $qmLocale)) }}" dir="{{ $qmDisplayValue($qmLocale === 'ar' ? 'rtl' : 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Queue Monitor</title>
    <link rel="stylesheet" href="{{ $qmDisplayValue(asset('vendor/queue-monitor/queue-monitor.css')) }}">
    <script src="{{ $qmDisplayValue(asset('vendor/queue-monitor/queue-monitor.js')) }}" defer></script>
</head>
<body>
<div class="qm-shell">
    <header class="qm-topbar">
        <a class="qm-brand" href="{{ $qmDisplayValue(route('queue-monitor.index')) }}">
            <span class="qm-brand-mark"><span></span><span></span><span></span></span>
            <span>
                <strong>Queue Monitor</strong>
                <small>Laravel queue operations</small>
            </span>
        </a>
        <div class="qm-topbar-meta">
            @include('queue-monitor::partials.theme')
            <a class="qm-supervisor-nav" href="{{ $qmDisplayValue(route('queue-monitor.compare')) }}">{{ $qmDisplayValue(__('queue-monitor::messages.compare', [], $qmLocale)) }}</a>
            <a class="qm-supervisor-nav" href="{{ $qmDisplayValue(route('queue-monitor.health')) }}">{{ $qmDisplayValue(__('queue-monitor::messages.health', [], $qmLocale)) }}</a>
            @foreach ($supervisorServers as $supervisorServer)
                <a class="qm-supervisor-nav" href="{{ $qmDisplayValue(route('queue-monitor.supervisor.index', ['server' => $supervisorServer])) }}">Supervisor · {{ $qmDisplayValue($qmDisplayValue($supervisorServer)) }}</a>
            @endforeach
            <span class="qm-live-dot"></span>
            <span>{{ $qmDisplayValue(__('queue-monitor::messages.overview', [], $qmLocale)) }}</span>
            <span class="qm-version">PACKAGE</span>
            @include('queue-monitor::partials.language')
        </div>
    </header>

    <main class="qm-main">
    @php
        $visibleWidgets = session('queue-monitor.dashboard.widgets', ['analytics', 'workers', 'distribution', 'capabilities']);
        $refreshInterval = (int) session('queue-monitor.dashboard.refresh', 0);
    @endphp
        <section class="qm-heading">
            <div>
                <p class="qm-eyebrow">{{ $qmDisplayValue(__('queue-monitor::messages.operations_center', [], $qmLocale)) }}</p>
                <h1>{{ $qmDisplayValue(__('queue-monitor::messages.queue_overview', [], $qmLocale)) }}</h1>
                <p class="qm-subtitle">{{ $qmDisplayValue(__('queue-monitor::messages.subtitle', [], $qmLocale)) }}</p>
            </div>
            <div class="qm-heading-actions">
                <span class="qm-updated"><span class="qm-live-dot"></span> {{ $qmDisplayValue(__('queue-monitor::messages.current', [], $qmLocale)) }}</span>
                <a class="qm-refresh" href="{{ $qmDisplayValue(request()->fullUrl()) }}" aria-label="{{ $qmDisplayValue(__('queue-monitor::messages.refresh', [], $qmLocale)) }}" title="{{ $qmDisplayValue(__('queue-monitor::messages.refresh', [], $qmLocale)) }}">↻</a>
            </div>
        </section>

        <details class="qm-panel qm-widget-preferences">
            <summary>{{ $qmDisplayValue(__('queue-monitor::messages.customize', [], $qmLocale)) }} <span>Choose which panels are visible in this browser session</span></summary>
            <form method="POST" action="{{ $qmDisplayValue(route('queue-monitor.preferences')) }}">
                @csrf
                @foreach (['analytics' => __('queue-monitor::messages.performance', [], $qmLocale), 'workers' => __('queue-monitor::messages.workers', [], $qmLocale), 'distribution' => __('queue-monitor::messages.distribution', [], $qmLocale), 'capabilities' => __('queue-monitor::messages.capabilities', [], $qmLocale)] as $widget => $label)
                    <label><input type="checkbox" name="widgets[]" value="{{ $qmDisplayValue($widget) }}" @checked(in_array($widget, $visibleWidgets, true))> {{ $qmDisplayValue($qmDisplayValue($label)) }}</label>
                @endforeach
                <label class="qm-refresh-preference"><span>{{ $qmDisplayValue(__('queue-monitor::messages.auto_refresh', [], $qmLocale)) }}</span><select name="refresh"><option value="0" @selected($refreshInterval === 0)>Off</option><option value="15" @selected($refreshInterval === 15)>15 seconds</option><option value="30" @selected($refreshInterval === 30)>30 seconds</option><option value="60" @selected($refreshInterval === 60)>60 seconds</option></select></label>
                <button type="submit">{{ $qmDisplayValue(__('queue-monitor::messages.save', [], $qmLocale)) }}</button>
            </form>
        </details>

        @if ($connectionCards !== [])
            <section class="qm-connections" aria-label="{{ $qmDisplayValue(__('queue-monitor::messages.queue', [], $qmLocale)) }} connections">
                @foreach ($connectionCards as $card)
                    <a class="qm-connection-card {{ $qmDisplayValue($card['connection'] === $connection ? 'is-selected' : '') }}"
                       href="{{ $qmDisplayValue(route('queue-monitor.index', ['connection' => $card['connection']])) }}">
                        <span class="qm-connection-icon">{{ $qmDisplayValue(strtoupper(substr($card['driver'], 0, 1))) }}</span>
                        <span class="qm-connection-copy">
                            <strong>{{ $qmDisplayValue($qmDisplayValue($card['connection'] ?? null)) }}</strong>
                            <small>{{ $qmDisplayValue(ucfirst($qmDisplayValue($card['driver'] ?? null))) }} driver</small>
                        </span>
                        @if ($card['error'])
                            <span class="qm-connection-state is-error">{{ $qmDisplayValue(__('queue-monitor::messages.unavailable', [], $qmLocale)) }}</span>
                        @else
                            <span class="qm-connection-state"><i></i> {{ $qmDisplayValue(__('queue-monitor::messages.connected', [], $qmLocale)) }}</span>
                        @endif
                    </a>
                @endforeach
            </section>
        @endif



        @if (session('status'))
            <div class="qm-alert qm-alert-success"><span class="qm-alert-icon">✓</span><div><strong>{{ $qmDisplayValue($qmDisplayValue(session('status'))) }}</strong></div></div>
        @endif

        @if ($connectionError)
            <div class="qm-alert qm-alert-error">
                <span class="qm-alert-icon">!</span>
                <div><strong>{{ $qmDisplayValue(__('queue-monitor::messages.connection', [], $qmLocale)) }} unavailable</strong><p>{{ $qmDisplayValue(__('queue-monitor::messages.connection_error', [], $qmLocale)) }}</p></div>
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
                    <p class="qm-eyebrow">{{ $qmDisplayValue(__('queue-monitor::messages.current_workload', [], $qmLocale)) }}</p>
                    <h2>{{ $qmDisplayValue($qmDisplayValue($queue ?? 'All queues')) }} <span class="qm-driver-tag">{{ $qmDisplayValue(ucfirst($qmDisplayValue($driverName))) }}</span></h2>
                </div>
                <span class="qm-total-label">{{ $qmDisplayValue(number_format($totalJobs)) }} {{ $qmDisplayValue(__('queue-monitor::messages.tracked_jobs', [], $qmLocale)) }}</span>
            </section>

            <section class="qm-stats-grid">
                <article class="qm-stat-card stat-pending">
                    <div class="qm-stat-top"><span>{{ $qmDisplayValue(__('queue-monitor::messages.pending', [], $qmLocale)) }}</span><span class="qm-stat-icon">◷</span></div>
                    <strong>{{ $qmDisplayValue(number_format($snapshot->pending)) }}</strong>
                    <small>{{ $qmDisplayValue(__('queue-monitor::messages.waiting_to_process', [], $qmLocale)) }}</small>
                    <div class="qm-stat-foot"><i></i> {{ $qmDisplayValue(__('queue-monitor::messages.ready_queue', [], $qmLocale)) }}</div>
                </article>
                <article class="qm-stat-card stat-running">
                    <div class="qm-stat-top"><span>{{ $qmDisplayValue(__('queue-monitor::messages.running', [], $qmLocale)) }}</span><span class="qm-stat-icon">↗</span></div>
                    <strong>{{ $qmDisplayValue(number_format($snapshot->running)) }}</strong>
                    <small>{{ $qmDisplayValue(__('queue-monitor::messages.reserved_workers', [], $qmLocale)) }}</small>
                    <div class="qm-stat-foot"><i></i> {{ $qmDisplayValue(__('queue-monitor::messages.in_progress', [], $qmLocale)) }}</div>
                </article>
                <article class="qm-stat-card stat-completed">
                    <div class="qm-stat-top"><span>{{ $qmDisplayValue(__('queue-monitor::messages.completed', [], $qmLocale)) }}</span><span class="qm-stat-icon">✓</span></div>
                    <strong>{{ $qmDisplayValue(number_format($snapshot->completed)) }}</strong>
                    <small>{{ $qmDisplayValue(__('queue-monitor::messages.successful_jobs', [], $qmLocale)) }}</small>
                    <div class="qm-stat-foot"><i></i> {{ $qmDisplayValue(__('queue-monitor::messages.history_dependent', [], $qmLocale)) }}</div>
                </article>
                <article class="qm-stat-card stat-failed">
                    <div class="qm-stat-top"><span>Failed</span><span class="qm-stat-icon">!</span></div>
                    <strong>{{ $qmDisplayValue(number_format($snapshot->failed)) }}</strong>
                    <small>{{ $qmDisplayValue(__('queue-monitor::messages.failed_jobs', [], $qmLocale)) }}</small>
                    <div class="qm-stat-foot"><i></i> {{ $qmDisplayValue(__('queue-monitor::messages.needs_attention', [], $qmLocale)) }}</div>
                </article>
            </section>

            @if ($sla['available'])
                <section class="qm-panel qm-sla-panel">
                    <div class="qm-section-heading">
                        <div><p class="qm-eyebrow">SERVICE LEVEL AGREEMENT</p><h2>{{ $qmDisplayValue(__('queue-monitor::messages.queue', [], $qmLocale)) }} wait-time targets</h2></div>
                        <span class="qm-total-label">Last {{ $qmDisplayValue($sla['window_hours']) }} hours</span>
                    </div>
                    <div class="qm-stats-grid">
                        <article class="qm-stat-card stat-completed">
                            <div class="qm-stat-top"><span>SLA compliance</span><span class="qm-stat-icon">✓</span></div>
                            <strong>{{ $qmDisplayValue($sla['rate'] === null ? '—' : number_format($sla['rate'], 1).'%') }}</strong>
                            <small>Jobs processed within target</small>
                        </article>
                        <article class="qm-stat-card stat-pending">
                            <div class="qm-stat-top"><span>Jobs measured</span><span class="qm-stat-icon">◷</span></div>
                            <strong>{{ $qmDisplayValue(number_format($sla['total'])) }}</strong>
                            <small>Jobs with recorded queue wait</small>
                        </article>
                        <article class="qm-stat-card stat-failed">
                            <div class="qm-stat-top"><span>SLA breaches</span><span class="qm-stat-icon">!</span></div>
                            <strong>{{ $qmDisplayValue(number_format($sla['breached'])) }}</strong>
                            <small>Wait time exceeded the queue target</small>
                        </article>
                    </div>
                    @if ($sla['queues'] !== [])
                        <div class="qm-sla-queues">
                            @foreach ($sla['queues'] as $slaQueue)
                                <div class="qm-sla-queue-row">
                                    <div><strong>{{ $qmDisplayValue($qmDisplayValue($slaQueue['queue'] ?? null)) }}</strong><small>Target: {{ $qmDisplayValue($qmDisplayValue($slaQueue['threshold'] ?? null)) }} sec</small></div>
                                    <span>{{ $qmDisplayValue(number_format($slaQueue['within'])) }} / {{ $qmDisplayValue(number_format($slaQueue['total'])) }} within target</span>
                                    <strong>{{ $qmDisplayValue(number_format($slaQueue['rate'], 1)) }}%</strong>
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
                    <div><h3>{{ $qmDisplayValue(__('queue-monitor::messages.job_activity', [], $qmLocale)) }}</h3><p>{{ $qmDisplayValue(__('queue-monitor::messages.completed', [], $qmLocale)) }} and failed jobs from persistent history</p></div>
                    <form method="GET" action="{{ $qmDisplayValue(route('queue-monitor.index')) }}" class="qm-period-form">
                        <input type="hidden" name="connection" value="{{ $qmDisplayValue($qmDisplayValue($connection)) }}">
                        <input type="hidden" name="queue" value="{{ $qmDisplayValue($qmDisplayValue($queue)) }}">
                        <input type="hidden" name="status" value="{{ $qmDisplayValue($qmDisplayValue($status)) }}">
                        <select name="period" aria-label="Analytics period" data-qm-auto-submit>
                            <option value="24h" @selected($period === '24h')>{{ $qmDisplayValue(__('queue-monitor::messages.last_24_hours', [], $qmLocale)) }}</option>
                            <option value="7d" @selected($period === '7d')>{{ $qmDisplayValue(__('queue-monitor::messages.last_7_days', [], $qmLocale)) }}</option>
                            <option value="30d" @selected($period === '30d')>{{ $qmDisplayValue(__('queue-monitor::messages.last_30_days', [], $qmLocale)) }}</option>
                        </select>
                    </form>
                </div>
                @if ($analytics['available'])
                    <div class="qm-analytics-summary">
                        <div><strong>{{ $qmDisplayValue(number_format($analytics['completed_count'])) }}</strong><span>{{ $qmDisplayValue(__('queue-monitor::messages.completed', [], $qmLocale)) }}</span></div>
                        <div><strong>{{ $qmDisplayValue(number_format($analytics['failed_count'])) }}</strong><span>Failed</span></div>
                        <div><strong>{{ $qmDisplayValue($analytics['average_duration'] === null ? '—' : number_format($analytics['average_duration']).' ms') }}</strong><span>Avg. duration</span></div>
                        <div><strong>{{ $qmDisplayValue($analytics['p95_duration'] === null ? '—' : number_format($analytics['p95_duration']).' ms') }}</strong><span>95th percentile</span></div>
                    </div>
                    @php $chartMax = max(1, ...$analytics['completed'], ...$analytics['failed']); @endphp
                    <div class="qm-activity-chart" role="img" aria-label="{{ $qmDisplayValue(__('queue-monitor::messages.completed', [], $qmLocale)) }} and failed job activity">
                        @foreach ($analytics['labels'] as $index => $label)
                            <div class="qm-chart-column" title="{{ $qmDisplayValue($qmDisplayValue($label)) }}: {{ $qmDisplayValue($analytics['completed'][$index]) }} completed, {{ $qmDisplayValue($analytics['failed'][$index]) }} failed">
                                <div class="qm-chart-bars">
                                    <i class="qm-chart-completed" style="height: {{ $qmDisplayValue(max(2, ($analytics['completed'][$index] / $chartMax) * 100)) }}%"></i>
                                    <i class="qm-chart-failed" style="height: {{ $qmDisplayValue(max(2, ($analytics['failed'][$index] / $chartMax) * 100)) }}%"></i>
                                </div>
                                @if (count($analytics['labels']) <= 7 || $index % 3 === 0)
                                    <small>{{ $qmDisplayValue($qmDisplayValue($label)) }}</small>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="qm-chart-legend"><span><i class="qm-chart-completed"></i>{{ $qmDisplayValue(__('queue-monitor::messages.completed', [], $qmLocale)) }}</span><span><i class="qm-chart-failed"></i>Failed</span></div>
                    @if ($analytics['top_classes'] !== [])
                        <div class="qm-slowest-jobs">
                            <strong>Slowest job classes by average duration</strong>
                            @foreach ($analytics['top_classes'] as $jobClass)
                                <div><span>{{ $qmDisplayValue($qmDisplayValue($jobClass['name'] ?? null)) }} <small>· {{ $qmDisplayValue(number_format($jobClass['count'])) }} jobs</small></span><b>{{ $qmDisplayValue(number_format($jobClass['average_duration'])) }} ms avg.</b></div>
                            @endforeach
                        </div>
                    @endif
                    @if ($analytics['slowest'] !== [])
                        <div class="qm-slowest-jobs">
                            <strong>Slowest completed jobs</strong>
                            @foreach ($analytics['slowest'] as $slowJob)
                                <div><span>{{ $qmDisplayValue($qmDisplayValue($slowJob->job_name) ?: $qmDisplayValue($slowJob->job_id)) }}</span><b>{{ $qmDisplayValue(number_format((int) $slowJob->duration_ms)) }} ms</b></div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="qm-analytics-empty">Enable persistent history to unlock activity charts and execution-time analytics.</div>
                @endif
            </article>
            <article class="qm-panel qm-health-summary">
                <div class="qm-panel-heading"><div><h3>{{ $qmDisplayValue(__('queue-monitor::messages.queue', [], $qmLocale)) }} health</h3><p>Based on configured thresholds</p></div></div>
                <div class="qm-health-state health-{{ $qmDisplayValue($health['status']) }}"><span></span>{{ $qmDisplayValue($qmDisplayValue($health['label'])) }}</div>
                <p>{{ $qmDisplayValue($qmDisplayValue($health['message'])) }}</p>
                @if ($analytics['available'])
                    <div class="qm-health-metric"><span>Failed jobs in period</span><strong>{{ $qmDisplayValue(number_format($analytics['failed_count'])) }}</strong></div>
                    <div class="qm-health-metric"><span>{{ $qmDisplayValue(__('queue-monitor::messages.pending', [], $qmLocale)) }} now</span><strong>{{ $qmDisplayValue(number_format($snapshot->pending)) }}</strong></div>
                @endif
            </article>
        </section>

        @endif

        @if (in_array('workers', $visibleWidgets, true) && $workerCapacity['available'])
            <section class="qm-panel qm-workers-panel">
                <div class="qm-panel-heading">
                    <div><h3>Worker capacity</h3><p>Live heartbeat counts · {{ $qmDisplayValue($qmDisplayValue($queue ? 'Queue: '.$queue : 'All queues')) }}</p></div>
                    <span class="qm-results-count">{{ $qmDisplayValue(count($workers)) }} tracked</span>
                </div>
                <div class="qm-worker-capacity-grid">
                    <article class="qm-capacity-card capacity-active"><span>Active workers</span><strong>{{ $qmDisplayValue(number_format($workerCapacity['active'])) }}</strong><small>{{ $qmDisplayValue(number_format($workerCapacity['active_for_queue'])) }} serving selected queue</small></article>
                    <article class="qm-capacity-card capacity-stale"><span>Stale workers</span><strong>{{ $qmDisplayValue(number_format($workerCapacity['stale'])) }}</strong><small>No heartbeat within configured threshold</small></article>
                    <article class="qm-capacity-card capacity-stopped"><span>Stopped workers</span><strong>{{ $qmDisplayValue(number_format($workerCapacity['stopped'])) }}</strong><small>Reported a graceful shutdown</small></article>
                    <article class="qm-capacity-card capacity-load"><span>{{ $qmDisplayValue(__('queue-monitor::messages.pending', [], $qmLocale)) }} jobs / active worker</span><strong>{{ $qmDisplayValue($workerCapacity['pending_per_active_worker'] === null ? '—' : number_format($workerCapacity['pending_per_active_worker'], 1)) }}</strong><small>{{ $qmDisplayValue($workerCapacity['pending'] === null ? 'Queue depth unavailable' : number_format($workerCapacity['pending']).' pending jobs in selected queue') }}</small></article>
                </div>
                @if ($workerCapacity['status'] === 'attention')
                    <div class="qm-worker-capacity-note is-warning">Jobs are waiting, but no active worker heartbeat is available for this queue.</div>
                @elseif ($workerCapacity['active_for_queue'] > 0)
                    <div class="qm-worker-capacity-note">{{ $qmDisplayValue(__('queue-monitor::messages.pending', [], $qmLocale)) }} jobs per worker is calculated from the selected queue depth and its active worker heartbeats.</div>
                @else
                    <div class="qm-worker-capacity-note">No active worker heartbeat is currently recorded for the selected queue.</div>
                @endif
                @if ($workers !== [])
                    <div class="qm-workers-grid">
                        @foreach ($workers as $worker)
                            <article class="qm-worker-card">
                                <div class="qm-worker-top"><strong>{{ $qmDisplayValue($qmDisplayValue($worker->hostname)) }}:{{ $qmDisplayValue($qmDisplayValue($worker->process_id)) }}</strong><span class="qm-worker-state worker-{{ $qmDisplayValue($worker->display_status) }}"><i></i>{{ $qmDisplayValue(ucfirst($worker->display_status)) }}</span></div>
                                <div class="qm-worker-meta"><span>{{ $qmDisplayValue($qmDisplayValue($worker->connection)) }}</span><span>{{ $qmDisplayValue(__('queue-monitor::messages.queue', [], $qmLocale)) }}: {{ $qmDisplayValue($qmDisplayValue($worker->queue) ?: '—') }}</span></div>
                                <small>Last heartbeat: {{ $qmDisplayValue($worker->heartbeat_at ? \Illuminate\Support\Carbon::parse($qmDisplayValue($worker->heartbeat_at))->diffForHumans() : 'Unknown') }}</small>
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
                        <div><h3>{{ $qmDisplayValue(__('queue-monitor::messages.queue', [], $qmLocale)) }} distribution</h3><p>How the current workload is split</p></div>
                        <span class="qm-panel-menu">•••</span>
                    </div>
                    <div class="qm-distribution-total"><strong>{{ $qmDisplayValue(number_format($snapshot->pending + $snapshot->running + $snapshot->completed + $snapshot->failed + $delayed)) }}</strong><span>Total jobs</span></div>
                    <div class="qm-bar" role="img" aria-label="{{ $qmDisplayValue(__('queue-monitor::messages.queue', [], $qmLocale)) }} state distribution">
                        <span class="bar-pending" style="width: {{ $qmDisplayValue($pendingWidth) }}%"></span>
                        <span class="bar-running" style="width: {{ $qmDisplayValue($runningWidth) }}%"></span>
                        <span class="bar-completed" style="width: {{ $qmDisplayValue($completedWidth) }}%"></span>
                        <span class="bar-failed" style="width: {{ $qmDisplayValue($failedWidth) }}%"></span>
                        <span class="bar-delayed" style="width: {{ $qmDisplayValue($delayedWidth) }}%"></span>
                    </div>
                    <div class="qm-legend">
                        <span><i class="legend-pending"></i>{{ $qmDisplayValue(__('queue-monitor::messages.pending', [], $qmLocale)) }} <b>{{ $qmDisplayValue(number_format($snapshot->pending)) }}</b></span>
                        <span><i class="legend-running"></i>{{ $qmDisplayValue(__('queue-monitor::messages.running', [], $qmLocale)) }} <b>{{ $qmDisplayValue(number_format($snapshot->running)) }}</b></span>
                        <span><i class="legend-completed"></i>{{ $qmDisplayValue(__('queue-monitor::messages.completed', [], $qmLocale)) }} <b>{{ $qmDisplayValue(number_format($snapshot->completed)) }}</b></span>
                        <span><i class="legend-failed"></i>Failed <b>{{ $qmDisplayValue(number_format($snapshot->failed)) }}</b></span>
                        <span><i class="legend-delayed"></i>{{ $qmDisplayValue(__('queue-monitor::messages.delayed', [], $qmLocale)) }} <b>{{ $qmDisplayValue(number_format($delayed)) }}</b></span>
                    </div>
                </article>
                @endif
                @if (in_array('capabilities', $visibleWidgets, true))
                <article class="qm-panel qm-health">
                    <div class="qm-panel-heading">
                        <div><h3>{{ $qmDisplayValue(__('queue-monitor::messages.connection', [], $qmLocale)) }} capabilities</h3><p>Available actions for this driver</p></div>
                        <span class="qm-capability-count">{{ $qmDisplayValue(count(array_filter($capabilities))) }}/{{ $qmDisplayValue(count($capabilities)) }}</span>
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
                            <div><span>{{ $qmDisplayValue($qmDisplayValue($label)) }}</span><b class="{{ $qmDisplayValue(($capabilities[$key] ?? false) ? 'is-enabled' : 'is-disabled') }}">{{ $qmDisplayValue(($capabilities[$key] ?? false) ? 'Available' : 'Not available') }}</b></div>
                        @endforeach
                    </div>
                </article>
                @endif
            </section>
            @endif

            @if ($driverName === 'beanstalkd')
                <div class="qm-info-note"><strong>Beanstalkd visibility:</strong> reserved job counts are available, but the Beanstalkd protocol does not expose reserved job payloads for listing. Only the next ready, delayed and buried job can be inspected per tube.</div>
            @endif

                    <section id="qm-explore-jobs" class="qm-filter-panel" tabindex="-1">
            <div class="qm-filter-intro">
                <span class="qm-filter-icon">⌕</span>
                <div>
                    <strong>{{ $qmDisplayValue(__('queue-monitor::messages.explore', [], $qmLocale)) }}</strong>
                    <small>{{ $qmDisplayValue(__('queue-monitor::messages.choose_connection', [], $qmLocale)) }}</small>
                </div>
            </div>
            <form class="qm-filters" method="GET" action="{{ $qmDisplayValue(route('queue-monitor.index')) }}#qm-explore-jobs">
                <label>
                    <span>{{ $qmDisplayValue(__('queue-monitor::messages.connection', [], $qmLocale)) }}</span>
                    <select name="connection" data-qm-auto-submit>
                        @forelse ($connectionNames as $connectionName)
                            <option value="{{ $qmDisplayValue($qmDisplayValue($connectionName)) }}" @selected($connectionName === $connection)>{{ $qmDisplayValue($qmDisplayValue($connectionName)) }}</option>
                        @empty
                            <option value="">{{ $qmDisplayValue(__('queue-monitor::messages.no_connections', [], $qmLocale)) }}</option>
                        @endforelse
                    </select>
                </label>
                <label>
                    <span>{{ $qmDisplayValue(__('queue-monitor::messages.queue', [], $qmLocale)) }}</span>
                    <select name="queue">
                        @forelse ($queues as $queueName)
                            <option value="{{ $qmDisplayValue($qmDisplayValue($queueName)) }}" @selected($queueName === $queue)>{{ $qmDisplayValue($qmDisplayValue($queueName)) }}</option>
                        @empty
                            <option value="">No queues found</option>
                        @endforelse
                    </select>
                </label>
                <label>
                    <span>{{ $qmDisplayValue(__('queue-monitor::messages.status', [], $qmLocale)) }}</span>
                    <select name="status">
                        <option value="">{{ $qmDisplayValue(__('queue-monitor::messages.all_states', [], $qmLocale)) }}</option>
                        @foreach (['pending' => __('queue-monitor::messages.pending', [], $qmLocale), 'running' => __('queue-monitor::messages.running', [], $qmLocale), 'delayed' => __('queue-monitor::messages.delayed', [], $qmLocale), 'failed' => __('queue-monitor::messages.failed', [], $qmLocale), 'completed' => __('queue-monitor::messages.completed', [], $qmLocale)] as $value => $label)
                            <option value="{{ $qmDisplayValue($value) }}" @selected($status === $value)>{{ $qmDisplayValue($qmDisplayValue($label)) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="qm-search-field">
                    <span>{{ $qmDisplayValue(__('queue-monitor::messages.search', [], $qmLocale)) }}</span>
                    <input type="search" name="search" value="{{ $qmDisplayValue($qmDisplayValue($search)) }}" placeholder="Name, ID, payload...">
                </label>
                <label class="qm-search-field">
                    <span>Job class</span>
                    <input type="search" name="job_class" value="{{ $qmDisplayValue($qmDisplayValue($jobClass)) }}" placeholder="App\\Jobs\\...">
                </label>
                <label>
                    <span>From</span>
                    <input type="date" name="date_from" value="{{ $qmDisplayValue($qmDisplayValue($dateFrom)) }}">
                </label>
                <label>
                    <span>To</span>
                    <input type="date" name="date_to" value="{{ $qmDisplayValue($qmDisplayValue($dateTo)) }}">
                </label>
                <label>
                    <span>Waiting longer than</span>
                    <select name="waiting_minutes">
                        <option value="">Any wait time</option>
                        @foreach ([5, 15, 30, 60] as $minutes)
                            <option value="{{ $minutes }}" @selected((int) request()->query('waiting_minutes') === $minutes)>{{ $minutes }} minutes</option>
                        @endforeach
                    </select>
                </label>
                <button class="qm-filter-button" type="submit">{{ $qmDisplayValue(__('queue-monitor::messages.apply', [], $qmLocale)) }} <span>→</span></button>
            </form>
        </section>

            <section id="qm-jobs-results" class="qm-panel qm-jobs-panel" tabindex="-1">
                <div class="qm-panel-heading qm-jobs-heading">
                    <div><h3>Recent jobs</h3><p>Inspect payloads and execution details for {{ $qmDisplayValue($qmDisplayValue($queue ?? 'this connection')) }}</p></div>
                    <span class="qm-results-count">{{ $qmDisplayValue($jobs->total()) }} results <span>·</span> {{ $qmDisplayValue($jobs->perPage()) }} per page</span>
                </div>
                @if ($jobs === [])
                    <div class="qm-empty">
                        <span class="qm-empty-icon">⌁</span>
                        <strong>No jobs to display</strong>
                        <p>There are no jobs matching the selected queue and filters, or this driver cannot expose that state.</p>
                    </div>
                @else
                    <form id="qm-compare-form" method="POST" action="{{ $qmDisplayValue(route('queue-monitor.jobs.compare', ['connection' => $connection])) }}" class="qm-compare-toolbar">
                        @csrf
                        <input type="hidden" name="queue" value="{{ $qmDisplayValue($qmDisplayValue($queue)) }}">
                        <span>{{ $qmDisplayValue(__('queue-monitor::messages.payload', [], $qmLocale)) }} comparison</span>
                        <button type="submit" onclick="return confirm('Compare the two selected job payloads?')">Compare selected (choose 2)</button>
                    </form>
                    <form id="qm-bulk-form" method="POST" action="{{ $qmDisplayValue(route('queue-monitor.jobs.bulk', ['connection' => $connection])) }}" class="qm-bulk-toolbar">
                        @csrf
                        <input type="hidden" name="queue" value="{{ $qmDisplayValue($qmDisplayValue($queue)) }}">
                        <label><span>Selected jobs</span><select name="action" required><option value="retry">Retry selected</option><option value="delete">Delete selected</option></select></label>
                        <button type="submit" onclick="return confirm('Apply this action to the selected jobs?')">Run bulk action</button>
                        <small>Up to 100 jobs per operation</small>
                    </form>
                    <div class="qm-table-wrap">
                        <table class="qm-table">
                            <thead><tr><th>Compare</th><th>Select</th><th>Job</th><th>{{ $qmDisplayValue(__('queue-monitor::messages.status', [], $qmLocale)) }}</th><th>Attempts</th><th>Created</th><th>Details</th><th>Actions</th></tr></thead>
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
                                    <td><input class="qm-job-select" type="checkbox" name="compare_ids[]" value="{{ $qmDisplayValue($qmDisplayValue($job->id)) }}" form="qm-compare-form" aria-label="Compare {{ $qmDisplayValue($qmDisplayValue($job->name) ?: $qmDisplayValue($job->id)) }}"></td>
                                    <td><input class="qm-job-select" type="checkbox" name="job_ids[]" value="{{ $qmDisplayValue($qmDisplayValue($job->id)) }}" form="qm-bulk-form" aria-label="Select {{ $qmDisplayValue($qmDisplayValue($job->name) ?: $qmDisplayValue($job->id)) }}"></td>
                                    <td>
                                        <a class="qm-job-name" href="{{ $qmDisplayValue(route('queue-monitor.jobs.show', ['connection' => $connection, 'jobId' => $job->id, 'queue' => $queue])) }}">{{ $qmDisplayValue($qmDisplayValue($job->name) ?: 'Unnamed job') }}</a>
                                        <code>{{ $qmDisplayValue(\Illuminate\Support\Str::limit($job->id, 22)) }}</code>
                                    </td>
                                    <td><span class="qm-status status-{{ $qmDisplayValue($jobStatusClass) }}"><i></i>{{ $qmDisplayValue(ucfirst($qmDisplayValue($job->status))) }}</span></td>
                                    <td>{{ $qmDisplayValue($qmDisplayValue($job->attempts) ?: '—') }}</td>
                                    <td>{{ $qmDisplayValue($job->createdAt ? date('d M Y, H:i', (int) $job->createdAt) : '—') }}</td>
                                    <td>
                                        @if ($job->payload !== null)
                                            <details class="qm-payload">
                                                <summary>{{ $qmDisplayValue(__('queue-monitor::messages.view_payload', [], $qmLocale)) }} <span>↗</span></summary>
                                                <pre>{{ $qmDisplayValue(json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}</pre>
                                            </details>
                                        @elseif ($job->exception)
                                            <details class="qm-payload">
                                                <summary>{{ $qmDisplayValue(__('queue-monitor::messages.view_exception', [], $qmLocale)) }} <span>↗</span></summary>
                                                <pre>{{ $qmDisplayValue($qmDisplayValue($job->exception)) }}</pre>
                                            </details>
                                        @else
                                            <span class="qm-muted">{{ $qmDisplayValue(__('queue-monitor::messages.no_details', [], $qmLocale)) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="qm-job-actions">
                                            @if (($capabilities['can_retry'] ?? false) && in_array($job->status, ['failed', 'delayed', 'running'], true))
                                                <form method="POST" action="{{ $qmDisplayValue(route('queue-monitor.jobs.retry', ['connection' => $connection, 'jobId' => $job->id])) }}">
                                                    @csrf
                                                    <input type="hidden" name="queue" value="{{ $qmDisplayValue($qmDisplayValue($queue)) }}">
                                                    <button class="qm-action-button action-retry" type="submit">Retry</button>
                                                </form>
                                            @endif
                                            @if (($capabilities['can_delete'] ?? false) && ($job->status !== 'completed' || $driverName === 'database'))
                                                <form method="POST" action="{{ $qmDisplayValue(route('queue-monitor.jobs.delete', ['connection' => $connection, 'jobId' => $job->id])) }}" onsubmit="return confirm('Delete this job?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="queue" value="{{ $qmDisplayValue($qmDisplayValue($queue)) }}">
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
                            <span>Page {{ $qmDisplayValue($jobs->currentPage()) }} of {{ $qmDisplayValue($jobs->lastPage()) }}</span>
                            <div>
                                @if ($jobs->onFirstPage())
                                    <span class="is-disabled">Previous</span>
                                @else
                                    <a href="{{ $qmDisplayValue($jobs->previousPageUrl()) }}#qm-explore-jobs">Previous</a>
                                @endif
                                @if ($jobs->hasMorePages())
                                    <a href="{{ $qmDisplayValue($jobs->nextPageUrl()) }}#qm-explore-jobs">Next</a>
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
    <script>
        window.setTimeout(() => window.location.reload(), {{ $qmDisplayValue($refreshInterval * 1000) }});
    </script>
@endif
    @include('queue-monitor::partials.theme')
</body>
</html>
