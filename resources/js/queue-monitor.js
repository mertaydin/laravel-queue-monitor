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
        if (document.getElementById('queue-monitor-theme-picker')) return;

        var wrapper = document.createElement('label');
        wrapper.id = 'queue-monitor-theme-picker';
        wrapper.innerHTML = '<span aria-hidden="true">◐</span><select aria-label="Theme"><option value="light">Day</option><option value="dark">Night</option><option value="system">System</option></select>';

        var select = wrapper.querySelector('select');
        select.value = preference;
        select.addEventListener('change', function () {
            preference = select.value;
            try { window.localStorage.setItem(themeKey, preference); } catch (error) {}
            applyTheme();
        });

        (document.querySelector('.qm-topbar-meta') || document.body).appendChild(wrapper);
    }

    document.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || target.tagName !== 'SELECT') return;

        if (target.name === 'locale' && target.form && target.form.id === 'queue-monitor-language-form') {
            var secure = window.location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = 'queue_monitor_locale=' + encodeURIComponent(target.value) + '; path=/; max-age=31536000; SameSite=Lax' + secure;
            target.form.submit();
            return;
        }

        if (target.hasAttribute('data-qm-auto-submit') && target.form) {
            target.form.submit();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountThemePicker, { once: true });
    } else {
        mountThemePicker();
    }

    if (window.matchMedia) {
        var media = window.matchMedia('(prefers-color-scheme: dark)');
        var onSystemThemeChange = function () { if (preference === 'system') applyTheme(); };
        if (media.addEventListener) media.addEventListener('change', onSystemThemeChange);
        else if (media.addListener) media.addListener(onSystemThemeChange);
    }
})();
