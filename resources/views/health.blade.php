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
    <title>{{ __('queue-monitor::messages.health', [], $qmLocale) }} · Queue Monitor</title>
    <link rel="stylesheet" href="{{ asset('vendor/queue-monitor/queue-monitor.css') }}">
</head>
<body>
<div class="qm-shell">
    <header class="qm-topbar">
        <a class="qm-brand" href="{{ route('queue-monitor.index') }}">
            <span class="qm-brand-mark"><span></span><span></span><span></span></span>
            <span><strong>Queue Monitor</strong><small>Laravel queue operations</small></span>
        </a>
        <div class="qm-topbar-meta"><a class="qm-supervisor-nav" href="{{ route('queue-monitor.index') }}">{{ __('queue-monitor::messages.dashboard', [], $qmLocale) }}</a><span>{{ __('queue-monitor::messages.diagnostics', [], $qmLocale) }}</span></div>
    </header>
    <main class="qm-main">
        <section class="qm-heading">
            <div>
                <p class="qm-eyebrow">OPERATIONS · DIAGNOSTICS</p>
                <h1>{{ __('queue-monitor::messages.health', [], $qmLocale) }}</h1>
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
    @include('queue-monitor::partials.language')
    @include('queue-monitor::partials.theme')
</body>
</html>
