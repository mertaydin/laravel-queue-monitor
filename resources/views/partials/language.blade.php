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
<style>
#queue-monitor-language-form{display:flex;align-items:center;flex:0 0 auto;margin-left:auto}
#queue-monitor-language-picker{position:static;display:flex;align-items:center;flex:0 0 auto;margin:0;z-index:auto}
#queue-monitor-language-picker select{max-width:125px;padding:7px 9px;border:1px solid #d9e0ea;border-radius:8px;background:#fff;color:#263348;font:600 12px system-ui}
html[data-theme="dark"] #queue-monitor-language-picker select{background:#182437;color:#e5eaf3;border-color:#39485e}
@media(max-width:600px){#queue-monitor-language-picker select{max-width:105px;padding:7px 6px}}
</style>
<script>
(function(){
    var form=document.getElementById('queue-monitor-language-form');
    var select=form?form.querySelector('select[name="locale"]'):null;
    if(select&&!select.dataset.queueMonitorBound){
        select.dataset.queueMonitorBound='1';
        select.addEventListener('change',function(){
            document.cookie='queue_monitor_locale='+encodeURIComponent(select.value)+'; path=/; max-age=31536000; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');
            form.submit();
        });
    }
})();
</script>
