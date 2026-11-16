<?php

namespace MertAydin\LaravelQueueMonitor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApplyDashboardTheme
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $contentType = (string) $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $html = $response->getContent();

        if (! is_string($html) || ! str_contains($html, '</head>') || ! str_contains($html, '</body>')) {
            return $response;
        }

        $styles = <<<'CSS'
<style id="queue-monitor-theme-css">
html[data-theme="dark"]{color-scheme:dark;background:#0b1220}
html[data-theme="dark"] body{background:#0b1220!important;color:#e5eaf3!important}
html[data-theme="dark"] .qm-shell,html[data-theme="dark"] .qm-main{color:#e5eaf3}
html[data-theme="dark"] .qm-topbar,html[data-theme="dark"] .qm-panel,html[data-theme="dark"] .qm-connection-card,html[data-theme="dark"] .qm-filter-panel,html[data-theme="dark"] .qm-stat-card,html[data-theme="dark"] .qm-jobs-panel,html[data-theme="dark"] .qm-widget-preferences,html[data-theme="dark"] .qm-analytics-panel,html[data-theme="dark"] .qm-health-summary{background:#151f30!important;border-color:#2b394d!important;color:#e5eaf3!important}
html[data-theme="dark"] .qm-topbar{border-bottom-color:#2b394d}
html[data-theme="dark"] .qm-heading h1,html[data-theme="dark"] .qm-section-heading h2,html[data-theme="dark"] .qm-panel-heading h3,html[data-theme="dark"] .qm-job-name,html[data-theme="dark"] .qm-stat-card>strong,html[data-theme="dark"] .qm-analytics-summary strong{color:#e5eaf3!important}
html[data-theme="dark"] .qm-table th{background:#101a29!important;color:#aab8cc!important}
html[data-theme="dark"] .qm-table td{color:#cbd5e1!important;border-color:#2b394d!important}
html[data-theme="dark"] .qm-table tbody tr:hover{background:#1d2b40!important}
html[data-theme="dark"] .qm-filters select,html[data-theme="dark"] .qm-filters input,html[data-theme="dark"] .qm-period-form select,html[data-theme="dark"] .qm-refresh-preference select{background:#101a29!important;color:#e5eaf3!important;border-color:#394a62!important}
html[data-theme="dark"] .qm-refresh,html[data-theme="dark"] .qm-action-button{background:#24334a!important;color:#dbe4f0!important;border-color:#40516a!important}
html[data-theme="dark"] .qm-filter-intro small,html[data-theme="dark"] .qm-subtitle,html[data-theme="dark"] .qm-updated,html[data-theme="dark"] .qm-total-label,html[data-theme="dark"] .qm-panel-heading p,html[data-theme="dark"] .qm-legend span,html[data-theme="dark"] .qm-capability-list>div,html[data-theme="dark"] .qm-footer{color:#9aa9bd!important}
html[data-theme="dark"] .qm-legend b{color:#dbe4f0!important}
html[data-theme="dark"] .qm-info-note{background:#1a2940!important;border-color:#344765!important;color:#c2cee0!important}
html[data-theme="dark"] .qm-info-note strong{color:#d3dcff!important}
html[data-theme="dark"] .qm-payload pre{background:#080f1b;border-color:#394a62}
#package-theme-picker{position:fixed;right:18px;bottom:18px;z-index:99999;display:flex;align-items:center;gap:8px;padding:7px 10px;border:1px solid #dbe2ec;border-radius:12px;background:#fff;color:#344153;box-shadow:0 8px 28px rgba(15,23,42,.16);font:600 12px/1.2 system-ui,sans-serif}
#package-theme-picker select{max-width:130px;border:0;background:transparent;color:inherit;font:inherit;outline:none;cursor:pointer}
html[data-theme="dark"] #package-theme-picker,html[data-theme="dark"] #package-theme-picker select{background:#172438;color:#e5eaf3;border-color:#40516a}
@media(max-width:600px){#package-theme-picker{right:10px;bottom:10px;padding:6px 8px}}
</style>
CSS;
        $earlyScript = <<<'JS'
<script id="queue-monitor-theme-early">
(function(){try{var p=localStorage.getItem('queue-monitor-theme');document.documentElement.dataset.theme=(p==='dark'||(p!=='light'&&window.matchMedia('(prefers-color-scheme: dark)').matches))?'dark':'light'}catch(e){}})();
</script>
JS;
        $pickerScript = <<<'JS'
<script id="queue-monitor-theme">
(function(){
    var key='queue-monitor-theme',root=document.documentElement,media=window.matchMedia('(prefers-color-scheme: dark)'),preference='system';
    var translations={tr:['Gündüz','Gece','Sistem'],en:['Light','Dark','System']};
    try{var saved=localStorage.getItem(key);if(['light','dark','system'].includes(saved))preference=saved}catch(e){}
    function apply(){root.dataset.theme=preference==='dark'||(preference==='system'&&media.matches)?'dark':'light'}
    apply();
    if(media.addEventListener)media.addEventListener('change',function(){if(preference==='system')apply()});
    function mount(){
        if(document.getElementById('package-theme-picker'))return;
        var language=(root.lang||'en').toLowerCase(),labels=translations[language]||translations.en;
        var wrapper=document.createElement('label');wrapper.id='package-theme-picker';wrapper.innerHTML='<span aria-hidden="true">◐</span><select aria-label="Theme"><option value="light">'+labels[0]+'</option><option value="dark">'+labels[1]+'</option><option value="system">'+labels[2]+'</option></select>';
        var select=wrapper.querySelector('select');select.value=preference;
        select.addEventListener('change',function(){preference=select.value;try{localStorage.setItem(key,preference)}catch(e){}apply()});
        document.body.appendChild(wrapper);
    }
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount,{once:true});else mount();
})();
</script>
JS;
        $html = str_ireplace('</head>', $styles.$earlyScript.'</head>', $html);
        $html = str_ireplace('</body>', $pickerScript.'</body>', $html);
        $response->setContent($html);

        return $response;
    }
}
