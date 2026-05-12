(function () {
    'use strict';
    var rest  = window.SeoCp.rest;
    var toast = window.SeoCp.toast;

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('seocp-tpl-list');
        if (!root) return;

        // Inline delete on each row.
        root.querySelectorAll('.seocp-tpl-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                var name = btn.getAttribute('data-name') || ('#' + id);
                if (!id) return;
                if (!confirm('Delete "' + name + '"?\n\nIf this is one of the default templates, it will stay deleted across upgrades. Use "Restore defaults" if you want it back.')) {
                    return;
                }
                btn.disabled = true;
                rest.del('templates/' + id)
                    .then(function () {
                        toast('Deleted "' + name + '"', 'success');
                        var row = btn.closest('tr');
                        if (row) row.parentNode.removeChild(row);
                    })
                    .catch(function (e) {
                        btn.disabled = false;
                        toast(e.message, 'danger');
                    });
            });
        });

        // Toolbar: restore defaults.
        var restore = document.getElementById('seocp-tpl-restore');
        if (restore) {
            restore.addEventListener('click', function () {
                if (!confirm('Restore all default templates that were previously deleted?')) return;
                restore.disabled = true;
                rest.post('templates/restore-defaults', {})
                    .then(function (r) {
                        var n = (r && r.added) || 0;
                        toast('Restored ' + n + ' template' + (n === 1 ? '' : 's') + '. Reloading…', 'success');
                        setTimeout(function () { window.location.reload(); }, 700);
                    })
                    .catch(function (e) {
                        restore.disabled = false;
                        toast(e.message, 'danger');
                    });
            });
        }
    });
})();
