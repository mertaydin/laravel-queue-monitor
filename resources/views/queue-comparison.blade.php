<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Queue comparison · Queue Monitor</title>
    <link rel="stylesheet" href="{{ asset('vendor/queue-monitor/queue-monitor.css') }}">
</head>
<body>
<div class="qm-shell">
    <header class="qm-topbar">
        <a class="qm-brand" href="{{ route('queue-monitor.index') }}">
            <span class="qm-brand-mark"><span></span><span></span><span></span></span>
            <span><strong>Queue Monitor</strong><small>Laravel queue operations</small></span>
        </a>
        <div class="qm-topbar-meta"><a class="qm-supervisor-nav" href="{{ route('queue-monitor.index') }}">Dashboard</a><span>Queue comparison</span></div>
    </header>
    <main class="qm-main">
        <section class="qm-heading">
            <div><p class="qm-eyebrow">OPERATIONS · OVERVIEW</p><h1>Queue comparison</h1><p class="qm-subtitle">Compare workload across every queue connection in one place.</p></div>
            <div class="qm-heading-actions"><a class="qm-refresh" href="{{ route('queue-monitor.compare') }}" title="Refresh comparison">↻</a></div>
        </section>
        <section class="qm-panel qm-comparison-panel">
            <div class="qm-panel-heading"><div><h3>Queue workload</h3><p>Sorted by pending jobs, highest first</p></div><span class="qm-results-count">{{ count($rows) }} queues</span></div>
            @if ($rows === [])
                <div class="qm-empty"><strong>No queue connections found</strong><p>Configure a supported queue driver to compare queue workloads.</p></div>
            @else
                <div class="qm-table-wrap">
                    <table class="qm-table">
                        <thead><tr><th>Connection</th><th>Driver</th><th>Queue</th><th>Pending</th><th>Running</th><th>Completed</th><th>Failed</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><strong>{{ $row['connection'] }}</strong></td>
                                <td>{{ ucfirst($row['driver']) }}</td>
                                <td>{{ $row['queue'] }}</td>
                                <td><strong>{{ number_format($row['pending']) }}</strong></td>
                                <td>{{ number_format($row['running']) }}</td>
                                <td>{{ number_format($row['completed']) }}</td>
                                <td><span class="qm-status {{ $row['failed'] > 0 ? 'status-failed' : 'status-completed' }}"><i></i>{{ number_format($row['failed']) }}</span></td>
                                <td>@if ($row['error']) <span class="qm-connection-state is-error">Unavailable</span> @else <a href="{{ route('queue-monitor.index', ['connection' => $row['connection'], 'queue' => $row['queue']]) }}">Open</a> @endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </main>
</div>
    @include('queue-monitor::partials.theme')
</body>
</html>
