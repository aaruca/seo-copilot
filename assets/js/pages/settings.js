(function () {
    'use strict';
    var rest = window.SeoCp.rest;
    var toast = window.SeoCp.toast;

    document.addEventListener('DOMContentLoaded', function () {
        // Activate the right pivot when ?tab= is present.
        var url = new URL(window.location.href);
        var tab = url.searchParams.get('tab');
        if (tab) {
            var btn = document.querySelector('.fl-pivot__tab[aria-controls="seocp-pivot-' + tab + '"]');
            if (btn) btn.click();
        }

        // Post Types & Fields page logic.
        document.querySelectorAll('.seocp-pt-row').forEach(function (row) {
            var head = row.querySelector('.seocp-pt-row__expand') || row.querySelector('.seocp-pt-row__head');
            head.addEventListener('click', function (e) {
                if (e.target.closest('input,label,button.fl-button--primary')) return;
                row.setAttribute('data-collapsed', row.getAttribute('data-collapsed') === 'true' ? 'false' : 'true');
            });
        });

        document.querySelectorAll('.seocp-pt-toggle').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var enabled = [];
                document.querySelectorAll('.seocp-pt-toggle:checked').forEach(function (b) { enabled.push(b.dataset.pt); });
                rest.post('post-types', { enabled: enabled }).then(function () {
                    toast('Post types updated.', 'success');
                }).catch(function (e) { toast(e.message, 'danger'); });
            });
        });

        document.querySelectorAll('.seocp-save-defaults').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var pt = btn.dataset.pt;
                var fields = [];
                document.querySelectorAll('.seocp-field-toggle[data-pt="' + pt + '"]:checked').forEach(function (cb) {
                    fields.push(cb.dataset.field);
                });
                rest.post('fields/' + encodeURIComponent(pt) + '/defaults', { fields: fields })
                    .then(function () { toast('Saved field defaults for ' + pt, 'success'); })
                    .catch(function (e) { toast(e.message, 'danger'); });
            });
        });

        var truncate = document.getElementById('seocp-truncate-runs');
        if (truncate) {
            truncate.addEventListener('click', function () {
                if (!confirm('Truncate all run logs?')) return;
                rest.del('runs').then(function () {
                    toast('Logs truncated.', 'success');
                    setTimeout(function () { window.location.reload(); }, 500);
                }).catch(function (e) { toast(e.message, 'danger'); });
            });
        }
    });
})();
