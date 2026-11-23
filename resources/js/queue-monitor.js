(function () {
    'use strict';

    var root = document.documentElement;
    var themeKey = 'queue-monitor-theme';
    var themes = ['light', 'dark', 'system'];
    var preference = 'system';

    try {
        var stored = window.localStorage.getItem(themeKey);
        if (themes.indexOf(stored) !== -1) preference = stored;
    } catch (error) {}

    function systemTheme() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme() {
        var theme = preference === 'system' ? systemTheme() : preference;
        root.setAttribute('data-theme', theme);
        root.style.colorScheme = theme;
    }

    function mountThemePicker() {
        applyTheme();

        var select = document.querySelector('#queue-monitor-theme-picker select');
        if (select) {
            select.value = preference;
            select.addEventListener('change', function () {
                preference = themes.indexOf(select.value) !== -1 ? select.value : 'system';
                try { window.localStorage.setItem(themeKey, preference); } catch (error) {}
                applyTheme();
            });
        }

        var localeSelect = document.querySelector('#queue-monitor-language-form select[name="locale"]');
        if (localeSelect && localeSelect.form) {
            localeSelect.addEventListener('change', function () {
                var secure = window.location.protocol === 'https:' ? '; Secure' : '';
                document.cookie = 'queue_monitor_locale=' + encodeURIComponent(localeSelect.value) + '; path=/; max-age=31536000; SameSite=Lax' + secure;

                if (typeof localeSelect.form.requestSubmit === 'function') {
                    localeSelect.form.requestSubmit();
                } else {
                    localeSelect.form.submit();
                }
            });
        }

        document.querySelectorAll('select[data-qm-auto-submit]').forEach(function (autoSubmit) {
            autoSubmit.addEventListener('change', function () {
                if (autoSubmit.form) autoSubmit.form.submit();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountThemePicker, { once: true });
    } else {
        mountThemePicker();
    }

    var refreshElement = document.querySelector('[data-qm-refresh-ms]');
    if (refreshElement) {
        var refreshDelay = parseInt(refreshElement.getAttribute('data-qm-refresh-ms'), 10);
        if (refreshDelay > 0) window.setTimeout(function () { window.location.reload(); }, refreshDelay);
    }

    if (window.matchMedia) {
        var media = window.matchMedia('(prefers-color-scheme: dark)');
        var onSystemThemeChange = function () { if (preference === 'system') applyTheme(); };
        if (media.addEventListener) media.addEventListener('change', onSystemThemeChange);
        else if (media.addListener) media.addListener(onSystemThemeChange);
    }
})();

/* Download the currently displayed, already-masked job details without another server request. */
(function () {
    'use strict';
    function addJobDetailDownloads() {
        document.querySelectorAll('.qm-payload').forEach(function (details) {
            if (details.querySelector('[data-qm-download]')) return;
            var pre = details.querySelector('pre');
            if (!pre) return;
            var button = document.createElement('button');
            button.type = 'button'; button.dataset.qmDownload = 'true';
            button.className = 'qm-action-button'; button.textContent = 'Download details';
            button.style.marginTop = '8px';
            button.addEventListener('click', function (event) {
                event.preventDefault(); event.stopPropagation();
                var text = pre.textContent || '', isJson = false;
                try { JSON.parse(text); isJson = true; } catch (error) {}
                var blob = new Blob([text], {type:isJson ? 'application/json;charset=utf-8' : 'text/plain;charset=utf-8'});
                var url = URL.createObjectURL(blob), link = document.createElement('a');
                link.href = url;
                link.download = 'queue-job-details-' + new Date().toISOString().replace(/[:.]/g, '-') + (isJson ? '.json' : '.txt');
                document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url);
            });
            details.appendChild(button);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', addJobDetailDownloads, {once:true});
    else addJobDetailDownloads();
})();
