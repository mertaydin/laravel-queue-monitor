<form id="queue-monitor-language-form" method="GET" action="{{ request()->url() }}">
    @foreach (request()->query() as $qmQueryKey => $qmQueryValue)
        @if ($qmQueryKey !== 'locale' && is_scalar($qmQueryValue))
            <input type="hidden" name="{{ $qmQueryKey }}" value="{{ $qmQueryValue }}">
        @endif
    @endforeach
    <label id="queue-monitor-language-picker">
        <span class="visually-hidden">{{ __('queue-monitor::messages.language', [], $qmLocale) }}</span>
        <select name="locale" aria-label="{{ __('queue-monitor::messages.language', [], $qmLocale) }}">
            @foreach ($qmAvailableLocales as $qmAvailableLocale)
                <option value="{{ $qmAvailableLocale }}" @selected($qmLocale === $qmAvailableLocale)>{{ $qmLanguageNames[$qmAvailableLocale] }}</option>
            @endforeach
        </select>
    </label>
</form>
