(function () {
    'use strict';
    var rest = window.SeoCp.rest;
    var toast = window.SeoCp.toast;
    var esc = window.SeoCp.escape;
    var widgets = window.SeoCp;

    var LEN_BUDGET = {
        rm_seo_title:        { min: 50, max: 60 },
        yoast_seo_title:     { min: 50, max: 60 },
        aioseo_title:        { min: 50, max: 60 },
        rm_meta_description: { min: 140, max: 160 },
        yoast_meta_description: { min: 140, max: 160 },
        aioseo_description:  { min: 140, max: 160 },
        rm_focus_keyword:    { min: 30, max: 200 },
        yoast_focus_keyword: { min: 30, max: 200 },
        aioseo_keyphrase:    { min: 30, max: 200 },
        seopress_seo_title:        { min: 50, max: 60 },
        seopress_meta_description: { min: 140, max: 160 },
        seopress_focus_keyword:    { min: 30, max: 200 },
        post_title:          { min: 30, max: 65 },
        post_excerpt:        { min: 60, max: 280 },
        featured_image_alt:  { min: 8, max: 125 }
    };
    function budgetFor(fid) { return LEN_BUDGET[fid] || { min: 0, max: 200 }; }

    var state = { post_id: 0, post_type: '', template_id: 0, picked: {}, fields: [], proposal: null, current: null, proposed: {}, applyChecked: {}, counters: {} };

    function $(id) { return document.getElementById(id); }

    function loadFields() {
        rest.get('fields/' + encodeURIComponent(state.post_type) + '?post_id=' + state.post_id).then(function (data) {
            state.fields = [];
            (data.groups || []).forEach(function (g) { g.fields.forEach(function (f) { state.fields.push(f); }); });
            state.defaults = data.defaults || [];
            renderFields();
        }).catch(function (e) { toast(e.message, 'danger'); });
    }

    function renderFields() {
        var host = $('seocp-mb-fields');
        host.innerHTML = '';
        var sel = $('seocp-mb-tpl');
        state.template_id = parseInt(sel.value, 10);
        var produces = (sel.options[sel.selectedIndex] && sel.options[sel.selectedIndex].dataset.produces || '').split(',').filter(Boolean);
        state.picked = {};
        var allowed = state.fields.filter(function (f) { return !produces.length || produces.indexOf(f.id) >= 0; });
        if (!allowed.length) {
            host.innerHTML = '<p class="fl-muted">No fields apply to this post type for this template.</p>';
            return;
        }
        allowed.forEach(function (f) {
            var checked = state.defaults.length ? state.defaults.indexOf(f.id) >= 0 : true;
            state.picked[f.id] = checked;
            var lbl = document.createElement('label');
            lbl.className = 'fl-choice';
            lbl.style.display = 'block';
            lbl.style.marginBottom = '4px';
            lbl.title = f.description || '';
            lbl.innerHTML = '<input type="checkbox" data-fid="' + esc(f.id) + '" ' + (checked ? 'checked' : '') + ' /><span>' + esc(f.label) + '</span>';
            lbl.querySelector('input').addEventListener('change', function (e) { state.picked[f.id] = e.target.checked; });
            host.appendChild(lbl);
        });
    }

    function generate() {
        var fields = Object.keys(state.picked).filter(function (k) { return state.picked[k]; });
        if (!fields.length) { toast('No fields ticked.', 'warning'); return; }
        $('seocp-mb-status').innerHTML = '<span class="fl-spinner"></span> ' + window.seocpData.i18n.generating;
        rest.post('optimize', { post_id: state.post_id, template_id: state.template_id, fields: fields })
            .then(function (data) {
                state.proposal = data.proposal || {};
                state.current  = data.current || {};
                state.proposed = Object.assign({}, state.proposal);
                state.applyChecked = {};
                Object.keys(state.proposal).forEach(function (k) { state.applyChecked[k] = true; });
                $('seocp-mb-status').textContent = '';
                renderOutput();
            })
            .catch(function (e) { $('seocp-mb-status').textContent = ''; toast(e.message, 'danger'); });
    }

    function renderOutput() {
        var host = $('seocp-mb-output');
        host.innerHTML = '';
        if (!state.proposal) return;
        Object.keys(state.proposal).forEach(function (fid) {
            var b = budgetFor(fid);
            var row = document.createElement('div');
            row.className = 'seocp-fieldblock';
            row.style.marginBottom = '8px';
            row.innerHTML =
                '<div class="seocp-fieldblock__head">' +
                    '<label class="fl-choice"><input type="checkbox" class="seocp-mb-apply" data-fid="' + esc(fid) + '" checked />' +
                        '<span><strong>' + esc(fid) + '</strong></span></label>' +
                '</div>' +
                '<textarea class="fl-textarea seocp-mb-proposed" data-fid="' + esc(fid) + '" rows="3">' + esc(state.proposed[fid]) + '</textarea>' +
                '<span class="fl-counter" data-counter="' + esc(fid) + '"></span>';
            host.appendChild(row);
            var counterHost = row.querySelector('[data-counter="' + fid + '"]');
            state.counters[fid] = widgets.counter(counterHost, b.min, b.max);
            state.counters[fid].update(state.proposed[fid]);
            row.querySelector('textarea').addEventListener('input', function (e) {
                state.proposed[fid] = e.target.value;
                state.counters[fid].update(e.target.value);
            });
            row.querySelector('input.seocp-mb-apply').addEventListener('change', function (e) { state.applyChecked[fid] = e.target.checked; });
        });
        var btn = document.createElement('button');
        btn.className = 'fl-button fl-button--primary fl-button--small';
        btn.textContent = 'Apply selected';
        btn.addEventListener('click', applySelected);
        host.appendChild(btn);
    }

    function applySelected() {
        var values = {};
        Object.keys(state.applyChecked).forEach(function (fid) { if (state.applyChecked[fid]) values[fid] = state.proposed[fid]; });
        if (!Object.keys(values).length) { toast('Nothing ticked.', 'warning'); return; }
        rest.post('apply', { post_id: state.post_id, template_id: state.template_id, fields_to_write: values })
            .then(function (r) { toast('Applied: ' + (r.written || []).join(', '), 'success'); })
            .catch(function (e) { toast(e.message, 'danger'); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = $('seocp-metabox');
        if (!root) return;
        state.post_id = parseInt(root.dataset.post, 10);
        state.post_type = root.dataset.pt;
        var sel = $('seocp-mb-tpl');
        if (!sel) return;
        sel.addEventListener('change', renderFields);
        $('seocp-mb-generate').addEventListener('click', generate);
        loadFields();
    });
})();
