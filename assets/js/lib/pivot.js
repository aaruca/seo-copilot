(function () {
    'use strict';
    document.addEventListener('click', function (e) {
        var tab = e.target.closest('.fl-pivot__tab');
        if (!tab) return;
        var pivot = tab.closest('.fl-pivot');
        if (!pivot) return;
        var panelId = tab.getAttribute('aria-controls');
        if (!panelId) return;
        pivot.querySelectorAll('.fl-pivot__tab').forEach(function (t) { t.setAttribute('aria-selected', 'false'); });
        tab.setAttribute('aria-selected', 'true');
        var scope = pivot.parentElement || document;
        scope.querySelectorAll('.fl-pivot__panel').forEach(function (p) { p.setAttribute('data-active', 'false'); });
        var panel = scope.querySelector('#' + panelId);
        if (panel) panel.setAttribute('data-active', 'true');
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', panelId.replace(/^seocp-pivot-/, ''));
            window.history.replaceState({}, '', url.toString());
        } catch (_) {}
    });
})();
