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
    <title>Compare job payloads · {{ __('queue-monitor::messages.queue', [], $qmLocale) }} Monitor</title>
    <link rel="stylesheet" href="{{ asset('vendor/queue-monitor/queue-monitor.css') }}">
</head>
<body>
<div class="qm-shell">
    <header class="qm-topbar">
        <a class="qm-brand" href="{{ route('queue-monitor.index', ['connection' => $connection, 'queue' => $queue]) }}">
            <span class="qm-brand-mark"><span></span><span></span><span></span></span>
            <span><strong>{{ __('queue-monitor::messages.queue', [], $qmLocale) }} Monitor</strong><small>Laravel queue operations</small></span>
        </a>
        <a class="qm-back-link" href="{{ route('queue-monitor.index', ['connection' => $connection, 'queue' => $queue]) }}">← {{ __('queue-monitor::messages.back', [], $qmLocale) }}</a>
    </header>
    <main class="qm-main qm-detail-main">
        <section class="qm-heading">
            <div><p class="qm-eyebrow">PAYLOAD INSPECTOR</p><h1>Compare jobs</h1><p class="qm-subtitle">Only masked payload values are compared and displayed.</p></div>
            <span class="qm-total-label">{{ count(array_filter($differences, fn ($difference) => $difference['different'])) }} differences</span>
        </section>
        <section class="qm-compare-layout">
            @foreach ($jobs as $index => $job)
                <article class="qm-panel qm-compare-card">
                    <h3>{{ $job->name ?: 'Unnamed job' }}</h3>
                    <p class="qm-muted">{{ $job->id }} · {{ ucfirst($job->status) }}</p>
                    <pre class="qm-detail-code">{{ json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                </article>
            @endforeach
        </section>
        <article class="qm-panel qm-detail-card" style="margin-top:14px">
            <h3>Field differences</h3>
            @if ($differences === [])
                <p class="qm-detail-empty">Both payloads are empty.</p>
            @else
                <div class="qm-table-wrap">
                    <table class="qm-compare-table">
                        <thead><tr><th>Field</th><th>Job 1</th><th>Job 2</th><th>Result</th></tr></thead>
                        <tbody>
                        @foreach ($differences as $difference)
                            <tr class="{{ $difference['different'] ? 'is-different' : '' }}">
                                <td><code>{{ $difference['key'] }}</code></td>
                                <td class="{{ $difference['first'] === null ? 'qm-compare-missing' : '' }}">{{ is_scalar($difference['first']) || $difference['first'] === null ? ($difference['first'] === null ? '—' : (string) $difference['first']) : json_encode($difference['first'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td>
                                <td class="{{ $difference['second'] === null ? 'qm-compare-missing' : '' }}">{{ is_scalar($difference['second']) || $difference['second'] === null ? ($difference['second'] === null ? '—' : (string) $difference['second']) : json_encode($difference['second'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</td>
                                <td>{{ $difference['different'] ? 'Different' : 'Same' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </article>
    </main>
</div>
    @include('queue-monitor::partials.language')
    @include('queue-monitor::partials.theme')
</body>
</html>
