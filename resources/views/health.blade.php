<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>System health · Queue Monitor</title>
    <link rel="stylesheet" href="{{ asset('vendor/queue-monitor/queue-monitor.css') }}">
</head>
<body>
<div class="qm-shell">
    <header class="qm-topbar">
        <a class="qm-brand" href="{{ route('queue-monitor.index') }}">
            <span class="qm-brand-mark"><span></span><span></span><span></span></span>
            <span><strong>Queue Monitor</strong><small>Laravel queue operations</small></span>
        </a>
        <div class="qm-topbar-meta"><a class="qm-supervisor-nav" href="{{ route('queue-monitor.index') }}">Dashboard</a><span>System diagnostics</span></div>
    </header>
    <main class="qm-main">
        <section class="qm-heading">
            <div>
                <p class="qm-eyebrow">OPERATIONS · DIAGNOSTICS</p>
                <h1>System health</h1>
                <p class="qm-subtitle">Run an on-demand check of database, queue connections, worker storage and configured Supervisor servers.</p>
            </div>
            <div class="qm-heading-actions"><a class="qm-refresh" href="{{ route('queue-monitor.health') }}" title="Run checks again">↻</a></div>
        </section>
        <section class="qm-health-overview health-{{ $summary }}">
            <span class="qm-health-indicator"></span>
            <div><strong>{{ ucfirst($summary) }}</strong><p>{{ count($checks) }} diagnostic checks completed · {{ now()->format('d M Y, H:i:s') }}</p></div>
        </section>
        <section class="qm-panel qm-diagnostics-panel">
            <div class="qm-panel-heading"><div><h3>Diagnostic results</h3><p>Checks run only when this page is opened or refreshed.</p></div></div>
            <div class="qm-diagnostic-list">
                @foreach ($checks as $check)
                    <article class="qm-diagnostic-item">
                        <span class="qm-diagnostic-mark diagnostic-{{ $check['status'] }}"></span>
                        <div><strong>{{ $check['name'] }}</strong><p>{{ $check['message'] }}</p></div>
                        <span class="qm-diagnostic-status diagnostic-{{ $check['status'] }}">{{ ucfirst($check['status']) }}</span>
                    </article>
                @endforeach
                @if ($checks === [])
                    <p class="qm-diagnostics-empty">No checks are configured yet.</p>
                @endif
            </div>
        </section>
        <p class="qm-diagnostics-note">This page performs read-only checks. It does not restart workers, modify jobs or change queue configuration.</p>
    </main>
</div>
    @include('queue-monitor::partials.theme')
</body>
</html>
