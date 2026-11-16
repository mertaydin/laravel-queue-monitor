@php
    $qmAvailableLocales = config('queue-monitor.dashboard.available_locales', ['tr', 'en', 'zh_CN', 'hi', 'es', 'ar']);
    $qmDefaultLocale = config('queue-monitor.dashboard.locale', 'en');
    $qmRequestedLocale = request()->query('locale', request()->cookie('queue_monitor_locale', $qmDefaultLocale));
    $qmLocale = in_array($qmRequestedLocale, $qmAvailableLocales, true) ? $qmRequestedLocale : $qmDefaultLocale;
    $qmLanguageNames = ['tr' => 'Türkçe', 'en' => 'English', 'es' => 'Español', 'zh_CN' => '简体中文', 'hi' => 'हिन्दी', 'ar' => 'العربية'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $qmLocale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Supervisor · {{ __('queue-monitor::messages.queue', [], $qmLocale) }} Monitor</title>
    <link rel="stylesheet" href="{{ asset('vendor/queue-monitor/queue-monitor.css') }}">
</head>
<body>
<div class="qm-shell">
    <header class="qm-topbar">
        <a class="qm-brand" href="{{ route('queue-monitor.index') }}">
            <span class="qm-brand-mark"><span></span><span></span><span></span></span>
            <span><strong>{{ __('queue-monitor::messages.queue', [], $qmLocale) }} Monitor</strong><small>Worker process management</small></span>
        </a>
        <a class="qm-back-link" href="{{ route('queue-monitor.index') }}">← {{ __('queue-monitor::messages.dashboard', [], $qmLocale) }}</a>
    </header>
    <main class="qm-main">
        <section class="qm-heading">
            <div><p class="qm-eyebrow">PROCESS CONTROL</p><h1>Supervisor workers</h1><p class="qm-subtitle">Server: {{ $server }} · Only configured programs are shown.</p></div>
            <a class="qm-refresh" href="{{ request()->fullUrl() }}" title="Refresh process status" aria-label="Refresh">↻</a>
        </section>

        @if (session('status'))
            <div class="qm-supervisor-notice">{{ session('status') }}</div>
        @endif
        @if ($error)
            <div class="qm-supervisor-error"><strong>Supervisor connection failed</strong><p>{{ $error }}</p></div>
        @endif

        <section class="qm-panel qm-supervisor-panel">
            <div class="qm-panel-heading"><div><h3>Configured processes</h3><p>Process status is read directly from Supervisor.</p></div><span class="qm-results-count">{{ count($processes) }} processes</span></div>
            @if ($processes === [])
                <div class="qm-detail-empty">No configured processes were returned. Check the server connection and allowed program names.</div>
            @else
                <div class="qm-table-wrap">
                    <table class="qm-table">
                        <thead><tr><th>Process</th><th>State</th><th>PID</th><th>Uptime / Details</th><th>Actions</th></tr></thead>
                        <tbody>
                        @foreach ($processes as $process)
                            <tr>
                                <td><strong>{{ $process['name'] ?? 'Unknown' }}</strong><small class="qm-table-subtitle">{{ $process['group'] ?? '' }}</small></td>
                                <td><span class="qm-worker-state {{ ($process['statename'] ?? '') === 'RUNNING' ? 'worker-active' : 'worker-stale' }}"><i></i>{{ $process['statename'] ?? 'UNKNOWN' }}</span></td>
                                <td>{{ $process['pid'] ?? '—' }}</td>
                                <td>{{ $process['description'] ?? '—' }}</td>
                                <td>
                                    <div class="qm-supervisor-actions">
                                        @foreach (['start' => 'Start', 'stop' => 'Stop', 'restart' => 'Restart'] as $action => $label)
                                            <form method="POST" action="{{ route('queue-monitor.supervisor.action', ['server' => $server, 'process' => $process['name'] ?? '', 'action' => $action]) }}">
                                                @csrf
                                                <button type="submit" class="qm-supervisor-button action-{{ $action }}" @disabled(($action === 'start' && ($process['statename'] ?? '') === 'RUNNING') || (in_array($action, ['stop', 'restart'], true) && ($process['statename'] ?? '') !== 'RUNNING')) onclick="return confirm('{{ $label }} {{ $process['name'] ?? 'this process' }}?')">{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        <p class="qm-supervisor-footnote">Process controls require the separate <code>manage{{ __('queue-monitor::messages.queue', [], $qmLocale) }}Workers</code> authorization gate. Configure only trusted Supervisor endpoints and explicitly allowed program names.</p>
    </main>
</div>
    @include('queue-monitor::partials.language')
    @include('queue-monitor::partials.theme')
</body>
</html>
