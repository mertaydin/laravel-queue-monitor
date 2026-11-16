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
    <title>{{ $job->name ?: 'Job details' }} · Queue Monitor</title>
    <link rel="stylesheet" href="{{ asset('vendor/queue-monitor/queue-monitor.css') }}">
</head>
<body>
<div class="qm-shell">
    <header class="qm-topbar">
        <a class="qm-brand" href="{{ route('queue-monitor.index', ['connection' => $connection, 'queue' => $queue]) }}">
            <span class="qm-brand-mark"><span></span><span></span><span></span></span>
            <span><strong>Queue Monitor</strong><small>Laravel queue operations</small></span>
        </a>
        <a class="qm-back-link" href="{{ route('queue-monitor.index', ['connection' => $connection, 'queue' => $queue]) }}">← {{ __('queue-monitor::messages.back', [], $qmLocale) }}</a>
    </header>
    <main class="qm-main qm-detail-main">
        <section class="qm-heading">
            <div><p class="qm-eyebrow">JOB INSPECTOR</p><h1>{{ $job->name ?: 'Unnamed job' }}</h1><p class="qm-subtitle">Full execution metadata and masked payload.</p></div>
            <span class="qm-status status-{{ $job->status }}">{{ ucfirst($job->status) }}</span>
        </section>
        <section class="qm-detail-grid">
            <article class="qm-panel qm-detail-card">
                <h3>{{ __('queue-monitor::messages.execution', [], $qmLocale) }}</h3>
                <dl class="qm-detail-list">
                    <div><dt>Job ID</dt><dd><code>{{ $job->id }}</code></dd></div>
                    <div><dt>{{ __('queue-monitor::messages.connection', [], $qmLocale) }}</dt><dd>{{ $connection }}</dd></div>
                    <div><dt>{{ __('queue-monitor::messages.queue', [], $qmLocale) }}</dt><dd>{{ $queue }}</dd></div>
                    <div><dt>Driver</dt><dd>{{ ucfirst($driverName) }}</dd></div>
                    <div><dt>Attempts</dt><dd>{{ $job->attempts ?? '—' }}</dd></div>
                    <div><dt>Created</dt><dd>{{ $job->createdAt ? date('d M Y, H:i:s', $job->createdAt) : '—' }}</dd></div>
                    <div><dt>Reserved</dt><dd>{{ $job->reservedAt ? date('d M Y, H:i:s', $job->reservedAt) : '—' }}</dd></div>
                    <div><dt>Finished</dt><dd>{{ $job->completedAt ? date('d M Y, H:i:s', $job->completedAt) : '—' }}</dd></div>
                    <div><dt>Duration</dt><dd>{{ $job->durationMilliseconds === null ? '—' : number_format($job->durationMilliseconds).' ms' }}</dd></div>
                </dl>
            </article>
            @if ($workflow['batch_id'] !== null || $workflow['chain_remaining'] > 0)
                <article class="qm-panel qm-detail-card">
                    <h3>Workflow context</h3>
                    <dl class="qm-detail-list">
                        @if ($workflow['batch_id'] !== null)
                            <div><dt>Batch ID</dt><dd><code>{{ $workflow['batch_id'] }}</code></dd></div>
                        @endif
                        @if ($workflow['chain_remaining'] > 0)
                            <div><dt>Remaining chained jobs</dt><dd>{{ $workflow['chain_remaining'] }}</dd></div>
                        @endif
                    </dl>
                    <p class="qm-detail-empty">Workflow metadata is captured when the job is queued. Chain counts show the number of follow-up jobs attached at that moment.</p>
                </article>
            @endif
            <article class="qm-panel qm-detail-card">
                <h3>{{ __('queue-monitor::messages.payload', [], $qmLocale) }}</h3>
                @if ($job->payload !== null)
                    <pre class="qm-detail-code">{{ json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <p class="qm-detail-empty">No payload is available for this job.</p>
                @endif
            </article>
        </section>
        @if ($job->exception)
            <article class="qm-panel qm-detail-card qm-exception-card"><h3>{{ __('queue-monitor::messages.exception', [], $qmLocale) }}</h3><pre class="qm-detail-code">{{ $job->exception }}</pre></article>
        @endif
    </main>
</div>
    @include('queue-monitor::partials.language')
    @include('queue-monitor::partials.theme')
</body>
</html>
