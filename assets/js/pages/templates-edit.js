(function () {
    'use strict';
    var rest = window.SeoCp.rest;
    var toast = window.SeoCp.toast;

    function collect(root) {
        var data = {
            name: root.querySelector('input[name="name"]').value.trim(),
            slug: root.querySelector('input[name="slug"]').value.trim(),
            description: root.querySelector('input[name="description"]').value.trim(),
            system_prompt: root.querySelector('textarea[name="system_prompt"]').value,
            user_template: root.querySelector('textarea[name="user_template"]').value,
            applies_to_post_types: [],
            produces: [],
            is_active: !!root.querySelector('input[name="is_active"]').checked
        };
        root.querySelectorAll('input[name="applies_to_post_types"]:checked').forEach(function (cb) { data.applies_to_post_types.push(cb.value); });
        root.querySelectorAll('input[name="produces"]:checked').forEach(function (cb) { data.produces.push(cb.value); });
        return data;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('seocp-template-edit');
        if (!root) return;
        var bootstrap = JSON.parse(root.getAttribute('data-bootstrap') || '{}');

        document.getElementById('seocp-template-save').addEventListener('click', function () {
            var payload = collect(root);
            if (!payload.name) { toast('Name is required.', 'warning'); return; }
            var promise = bootstrap.id ? rest.put('templates/' + bootstrap.id, payload) : rest.post('templates', payload);
            promise.then(function (saved) {
                toast('Template saved.', 'success');
                if (!bootstrap.id && saved && saved.id) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('action', 'edit');
                    url.searchParams.set('id', saved.id);
                    window.location.href = url.toString();
                }
            }).catch(function (e) { toast(e.message, 'danger'); });
        });

        var del = document.getElementById('seocp-template-delete');
        if (del) {
            del.addEventListener('click', function () {
                if (!bootstrap.id) return;
                if (!confirm('Delete this template? This cannot be undone.')) return;
                rest.del('templates/' + bootstrap.id).then(function () {
                    window.location.href = window.seocpData.adminUrl + '?page=seo-copilot-templates';
                }).catch(function (e) { toast(e.message, 'danger'); });
            });
        }
    });
})();
