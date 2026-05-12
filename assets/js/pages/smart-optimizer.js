(function () {
    'use strict';
    var rest = window.SeoCp.rest;
    var toast = window.SeoCp.toast;
    var esc = window.SeoCp.escape;
    var widgets = window.SeoCp;

    /* Length budgets per field id family — used by counters + SERP preview.
       Multi-keyword focus fields use a wider budget (3–5 keywords). */
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

    var state = {
        step: 0,
        post: null,
        post_type: '',
        templates: [],
        template: null,
        fields: [],
        defaults: [],
        picked: {},
        proposal: null,
        current: null,
        counters: {},
        proposed: {},
        applyChecked: {},
        appliedFields: {}      // fid => true once successfully written
    };

    function $(id) { return document.getElementById(id); }
    function debounce(fn, ms) { var t; return function () { clearTimeout(t); var a = arguments; t = setTimeout(function () { fn.apply(null, a); }, ms); }; }

    function gotoStep(i) {
        state.step = i;
        var root = $('seocp-smart');
        widgets.setStep(root, i);
        widgets.showPanel(root, i);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ---------- STEP 1: post picker ---------- */
    function renderResults(items) {
        var host = $('seocp-smart-results');
        host.innerHTML = '';
        if (!items.length) {
            host.innerHTML = '<p class="fl-muted">No posts found.</p>';
            return;
        }
        items.forEach(function (p) {
            var snap = p.snapshot || {};
            var title_state = snap.title_len === 0 ? 'danger' : (snap.title_len >= 50 && snap.title_len <= 60 ? 'success' : 'warning');
            var desc_state  = snap.desc_len  === 0 ? 'danger' : (snap.desc_len  >= 140 && snap.desc_len <= 160 ? 'success' : 'warning');
            var focus_state = snap.has_focus ? 'success' : 'danger';
            var thumb = p.thumb ? ' style="background-image:url(\'' + esc(p.thumb) + '\');"' : '';
            var row = document.createElement('div');
            row.className = 'fl-selectable';
            row.setAttribute('role', 'button');
            row.setAttribute('tabindex', '0');
            row.setAttribute('data-id', p.id);
            row.innerHTML =
                '<div class="fl-selectable__thumb"' + thumb + '></div>' +
                '<div class="fl-selectable__body">' +
                    '<div class="fl-selectable__title">' + esc(p.title || ('#' + p.id)) + '</div>' +
                    '<div class="fl-selectable__meta">' +
                        '<span class="fl-badge fl-badge--' + title_state + '">title ' + snap.title_len + '</span> ' +
                        '<span class="fl-badge fl-badge--' + desc_state  + '">desc ' + snap.desc_len  + '</span> ' +
                        '<span class="fl-badge fl-badge--' + focus_state + '">focus ' + (snap.has_focus ? '✓' : '—') + '</span> ' +
                        '<span class="fl-muted" style="margin-left:8px;">' + esc(p.status || '') + '</span>' +
                    '</div>' +
                '</div>';
            row.addEventListener('click', function () { selectPost(p); });
            row.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectPost(p); } });
            host.appendChild(row);
        });
    }

    function selectPost(p) {
        state.post = p;
        document.querySelectorAll('#seocp-smart-results .fl-selectable').forEach(function (n) {
            n.setAttribute('aria-selected', String(parseInt(n.getAttribute('data-id'), 10) === p.id));
        });
        $('seocp-smart-picked').textContent = '✓ ' + p.title + ' (#' + p.id + ')';
        $('seocp-smart-next-1').disabled = false;
    }

    var searchPosts = debounce(function () {
        if (!state.post_type) return;
        var q = $('seocp-smart-search').value.trim();
        rest.get('posts?post_type=' + encodeURIComponent(state.post_type) + '&q=' + encodeURIComponent(q) + '&limit=20')
            .then(function (data) { renderResults(data.items || []); })
            .catch(function (e) { toast(e.message, 'danger'); });
    }, 250);

    /* ---------- STEP 2: template + fields ---------- */
    function renderTemplates() {
        var applicable = state.templates.filter(function (t) {
            return !t.applies_to_post_types.length || t.applies_to_post_types.indexOf(state.post_type) >= 0;
        });
        var host = $('seocp-smart-tpl-list');
        host.innerHTML = '';
        if (!applicable.length) {
            host.innerHTML = '<p class="fl-muted">No templates apply to this post type yet.</p>';
            return;
        }
        applicable.forEach(function (t) {
            var row = document.createElement('label');
            row.className = 'fl-selectable';
            row.innerHTML =
                '<input type="radio" name="seocp-smart-tpl-radio" value="' + t.id + '" style="margin-right:8px;" />' +
                '<div class="fl-selectable__body">' +
                    '<div class="fl-selectable__title">' + esc(t.name) + '</div>' +
                    '<div class="fl-selectable__meta">' + esc(t.description || '') + ' • <strong>' + t.produces.length + '</strong> field(s)</div>' +
                '</div>';
            row.querySelector('input').addEventListener('change', function () {
                state.template = t;
                document.querySelectorAll('#seocp-smart-tpl-list .fl-selectable').forEach(function (n) { n.setAttribute('aria-selected', 'false'); });
                row.setAttribute('aria-selected', 'true');
                renderFields();
            });
            host.appendChild(row);
        });
        var first = host.querySelector('input[type="radio"]');
        if (first) { first.checked = true; first.dispatchEvent(new Event('change')); }
    }

    function loadFields() {
        return rest.get('fields/' + encodeURIComponent(state.post_type) + '?post_id=' + state.post.id).then(function (data) {
            state.fields = [];
            (data.groups || []).forEach(function (g) { g.fields.forEach(function (f) { f._group = g.group; state.fields.push(f); }); });
            state.defaults = data.defaults || [];
        });
    }

    function renderFields() {
        if (!state.template) return;
        var host = $('seocp-smart-fields');
        host.innerHTML = '';
        var produces = state.template.produces || [];
        var allowed = state.fields.filter(function (f) { return !produces.length || produces.indexOf(f.id) >= 0; });
        if (!allowed.length) {
            host.innerHTML = '<p class="fl-muted">This template doesn\'t produce any field that applies to this post type.</p>';
            $('seocp-smart-generate').disabled = true;
            return;
        }
        state.picked = {};
        allowed.forEach(function (f) {
            state.picked[f.id] = state.defaults.length ? state.defaults.indexOf(f.id) >= 0 : true;
            var lbl = document.createElement('label');
            lbl.className = 'fl-choice';
            lbl.title = f.description || '';
            lbl.innerHTML =
                '<input type="checkbox" data-fid="' + esc(f.id) + '" ' + (state.picked[f.id] ? 'checked' : '') + ' />' +
                '<span><strong>' + esc(f.label) + '</strong> <span class="fl-muted">(' + esc(f.id) + ')</span></span>';
            lbl.querySelector('input').addEventListener('change', function (e) {
                state.picked[f.id] = e.target.checked;
                updateGenerateState();
            });
            host.appendChild(lbl);
        });
        updateGenerateState();
    }

    function pickedIds() { return Object.keys(state.picked).filter(function (k) { return state.picked[k]; }); }

    function updateGenerateState() {
        var n = pickedIds().length;
        $('seocp-smart-generate').disabled = n === 0;
        $('seocp-smart-cost').textContent = n
            ? (n + ' field' + (n === 1 ? '' : 's') + ' • ~' + Math.max(1, Math.round(n * 0.002 * 100) / 100) + '¢ est.')
            : 'No fields selected';
    }

    /* ---------- STEP 3: review ---------- */
    function generate() {
        var fields = pickedIds();
        if (!fields.length) { toast('Pick at least one field.', 'warning'); return; }
        gotoStep(2);
        var bar = $('seocp-smart-status-bar');
        bar.hidden = false;
        bar.className = 'fl-message-bar';
        bar.innerHTML = '<span class="fl-spinner"></span><span>' + window.seocpData.i18n.generating + '</span>';
        $('seocp-smart-output').innerHTML = '';
        $('seocp-smart-serp-card').hidden = true;
        $('seocp-smart-apply').disabled = true;
        state.appliedFields = {};

        rest.post('optimize', { post_id: state.post.id, template_id: state.template.id, fields: fields })
            .then(function (data) {
                state.proposal = data.proposal || {};
                state.current  = data.current || {};
                state.proposed = Object.assign({}, state.proposal);
                state.applyChecked = {};
                Object.keys(state.proposal).forEach(function (k) { state.applyChecked[k] = true; });
                bar.className = 'fl-message-bar fl-message-bar--success';
                bar.innerHTML = '<span>✓ ' + window.seocpData.i18n.generated + ' Estimated: ' +
                    (data.run.tokens_in || 0) + ' / ' + (data.run.tokens_out || 0) + ' tokens • $' +
                    (data.run.cost ? Number(data.run.cost).toFixed(4) : '0.0000') + '</span>';
                renderProposal();
            })
            .catch(function (e) {
                bar.className = 'fl-message-bar fl-message-bar--danger';
                bar.innerHTML = '<span>' + esc(e.message) + '</span>';
            });
    }

    function pickTitleField()  { return ['rm_seo_title','yoast_seo_title','aioseo_title','seopress_seo_title'].find(function (k) { return k in state.proposed; }); }
    function pickDescField()   { return ['rm_meta_description','yoast_meta_description','aioseo_description','seopress_meta_description'].find(function (k) { return k in state.proposed; }); }

    function renderProposal() {
        var keys = Object.keys(state.proposal);
        if (!keys.length) {
            $('seocp-smart-output').innerHTML = '<div class="fl-message-bar fl-message-bar--warning">No fields were returned.</div>';
            return;
        }

        var titleKey = pickTitleField();
        var descKey  = pickDescField();
        if (titleKey || descKey) {
            $('seocp-smart-serp-card').hidden = false;
            state._serp = widgets.serp($('seocp-smart-serp'), {
                url: state.post.permalink,
                title: titleKey ? state.proposed[titleKey] : (state.post.title || ''),
                description: descKey ? state.proposed[descKey] : ''
            });
            state._titleKey = titleKey;
            state._descKey = descKey;
        }

        var host = $('seocp-smart-output');
        host.innerHTML = '';
        keys.forEach(function (fid) {
            var current = (state.current && state.current[fid]) || '';
            var proposed = state.proposal[fid];
            var b = budgetFor(fid);
            var block = document.createElement('div');
            block.className = 'seocp-fieldblock';
            block.setAttribute('data-fid', fid);
            block.innerHTML =
                '<div class="seocp-fieldblock__head">' +
                    '<label class="fl-choice"><input type="checkbox" class="js-apply" data-fid="' + esc(fid) + '" checked />' +
                        '<span><strong>' + esc(fid) + '</strong></span></label>' +
                    '<span class="js-applied-badge"></span>' +
                    '<span class="fl-spacer"></span>' +
                    '<span class="fl-muted fl-text-200">target ' + b.min + '–' + b.max + ' chars</span>' +
                '</div>' +
                '<div class="seocp-diff">' +
                    '<div class="seocp-diff__col"><h4>Current</h4><pre class="seocp-diff__current js-current">' + esc(current || '—') + '</pre></div>' +
                    '<div class="seocp-diff__col">' +
                        '<h4>Proposed (editable)</h4>' +
                        '<textarea class="fl-textarea js-proposed" data-fid="' + esc(fid) + '" rows="3">' + esc(proposed) + '</textarea>' +
                        '<span class="fl-counter" data-counter="' + esc(fid) + '"></span>' +
                    '</div>' +
                '</div>';
            host.appendChild(block);

            var counterHost = block.querySelector('[data-counter="' + fid + '"]');
            state.counters[fid] = widgets.counter(counterHost, b.min, b.max);
            state.counters[fid].update(proposed);

            block.querySelector('textarea.js-proposed').addEventListener('input', function (e) {
                state.proposed[fid] = e.target.value;
                state.counters[fid].update(e.target.value);
                if (state._serp) {
                    if (fid === state._titleKey) state._serp.update({ title: e.target.value });
                    if (fid === state._descKey)  state._serp.update({ description: e.target.value });
                }
            });
            block.querySelector('input.js-apply').addEventListener('change', function (e) {
                state.applyChecked[fid] = e.target.checked;
                updateApplyButton();
            });
        });
        updateApplyButton();
    }

    function updateApplyButton() {
        var pendingIds = Object.keys(state.applyChecked).filter(function (k) {
            return state.applyChecked[k] && !state.appliedFields[k];
        });
        var n = pendingIds.length;
        $('seocp-smart-apply').disabled = n === 0;
        $('seocp-smart-apply').textContent = n ? ('Apply ' + n + ' field' + (n === 1 ? '' : 's')) : 'All selected fields applied';
    }

    function applySelected() {
        var values = {};
        Object.keys(state.applyChecked).forEach(function (fid) {
            if (state.applyChecked[fid] && !state.appliedFields[fid]) {
                values[fid] = state.proposed[fid];
            }
        });
        if (!Object.keys(values).length) return;

        var btn = $('seocp-smart-apply');
        btn.disabled = true;
        var originalLabel = btn.textContent;
        btn.innerHTML = '<span class="fl-spinner" style="margin-right:6px;"></span>Applying…';

        rest.post('apply', { post_id: state.post.id, template_id: state.template.id, fields_to_write: values })
            .then(function (r) {
                var written = r.written || [];
                lockAppliedFields(written, values);
                renderApplySuccess(written);
                toast('Applied: ' + written.join(', '), 'success');
                btn.textContent = originalLabel;
                updateApplyButton();
            })
            .catch(function (e) {
                btn.disabled = false;
                btn.textContent = originalLabel;
                renderApplyError(e.message);
                toast(e.message, 'danger');
            });
    }

    function lockAppliedFields(written, valuesSent) {
        written.forEach(function (fid) {
            state.appliedFields[fid] = true;
            var block = document.querySelector('.seocp-fieldblock[data-fid="' + fid + '"]');
            if (!block) return;
            block.setAttribute('data-applied', 'true');
            block.style.borderColor = 'var(--seocp-color-success)';
            block.style.boxShadow = '0 0 0 1px var(--seocp-color-success)';
            var badge = block.querySelector('.js-applied-badge');
            if (badge) badge.innerHTML = '<span class="fl-badge fl-badge--success">✓ Applied</span>';
            var ta = block.querySelector('textarea.js-proposed');
            if (ta) { ta.disabled = true; }
            var cb = block.querySelector('input.js-apply');
            if (cb) { cb.disabled = true; cb.checked = true; }
            var current = block.querySelector('.js-current');
            if (current) current.textContent = valuesSent[fid] || current.textContent;
        });
    }

    function renderApplySuccess(written) {
        var bar = $('seocp-smart-status-bar');
        bar.hidden = false;
        bar.className = 'fl-message-bar fl-message-bar--success';
        var permalink = state.post && state.post.permalink ? state.post.permalink : '';
        var editUrl = window.seocpData.adminUrl + '?page=&action=edit';
        // Build a richer success bar.
        var html = '<div style="display:flex;flex-direction:column;gap:4px;width:100%;">' +
            '<div><strong>✓ Saved to "' + esc(state.post.title || ('#' + state.post.id)) + '"</strong> — ' +
                written.length + ' field' + (written.length === 1 ? '' : 's') + ' written: ' +
                '<span class="fl-mono">' + esc(written.join(', ')) + '</span>' +
            '</div>' +
            '<div class="fl-row" style="gap:12px;">' +
                (permalink ? '<a class="fl-button fl-button--small" href="' + esc(permalink) + '" target="_blank" rel="noopener">View post ↗</a>' : '') +
                '<button id="seocp-smart-restart" type="button" class="fl-button fl-button--small">Optimize another post</button>' +
            '</div>' +
        '</div>';
        bar.innerHTML = html;
        var restart = document.getElementById('seocp-smart-restart');
        if (restart) restart.addEventListener('click', resetWizard);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function renderApplyError(msg) {
        var bar = $('seocp-smart-status-bar');
        bar.hidden = false;
        bar.className = 'fl-message-bar fl-message-bar--danger';
        bar.innerHTML = '<span><strong>Apply failed:</strong> ' + esc(msg) + '</span>';
    }

    function resetWizard() {
        state.post = null;
        state.template = null;
        state.proposal = null;
        state.current = null;
        state.proposed = {};
        state.applyChecked = {};
        state.appliedFields = {};
        $('seocp-smart-search').value = '';
        $('seocp-smart-pt').value = '';
        $('seocp-smart-results').innerHTML = '';
        $('seocp-smart-picked').textContent = '';
        $('seocp-smart-next-1').disabled = true;
        $('seocp-smart-output').innerHTML = '';
        $('seocp-smart-status-bar').hidden = true;
        $('seocp-smart-serp-card').hidden = true;
        gotoStep(0);
    }

    /* ---------- bootstrap ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        var root = $('seocp-smart');
        if (!root) return;
        try { state.templates = JSON.parse(root.getAttribute('data-templates') || '[]'); }
        catch (_) { state.templates = []; }

        widgets.stepperJumps(root, function (i) { gotoStep(i); });

        $('seocp-smart-pt').addEventListener('change', function (e) {
            state.post_type = e.target.value;
            state.post = null;
            $('seocp-smart-search').value = '';
            $('seocp-smart-picked').textContent = '';
            $('seocp-smart-next-1').disabled = true;
            if (state.post_type) searchPosts();
            else $('seocp-smart-results').innerHTML = '';
        });
        $('seocp-smart-search').addEventListener('input', searchPosts);
        $('seocp-smart-next-1').addEventListener('click', function () {
            if (!state.post) return;
            loadFields().then(function () { renderTemplates(); gotoStep(1); }).catch(function (e) { toast(e.message, 'danger'); });
        });
        $('seocp-smart-back-1').addEventListener('click', function () { gotoStep(0); });
        $('seocp-smart-back-2').addEventListener('click', function () { gotoStep(1); });
        $('seocp-smart-generate').addEventListener('click', generate);
        $('seocp-smart-apply').addEventListener('click', applySelected);
    });
})();
