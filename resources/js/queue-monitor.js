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
