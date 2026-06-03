(function () {
    'use strict';
    var rest = window.SeoCp.rest;
    var toast = window.SeoCp.toast;
    var esc = window.SeoCp.escape;
    var widgets = window.SeoCp;

    var state = {
        step: 0,
        templates: [],
        mode: 'apply',          // 'apply' (auto-apply) or 'review' (queue to Pending Review)
        dispatch: 'sync',       // 'sync' (per-post HTTP) or 'batch' (OpenAI Batch API)
        // filter (persisted across pagination)
        filter: { post_type: '', status: 'publish', q: '', preset: '' },
        // pagination
        page: 1,
        perPage: 20,
        total: 0,
        totalPages: 1,
        items: [],          // current page rows
        // selection
        selected: {},       // post_id -> bool (individual mode)
        allMatching: false, // when true, ignore `selected` and use the filter spec server-side
        // template + fields
        template: null,
        fields: [],
        defaults: [],
        picked: {},
        // batch
        batch_id: null,
        pollTimer: null
    };

    function $(id) { return document.getElementById(id); }
    function fmtNumber(n) { return (n || 0).toLocaleString(); }

    function gotoStep(i) {
        state.step = i;
        var root = $('seocp-bulk');
        widgets.setStep(root, i);
        widgets.showPanel(root, i);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ---------- STEP 1: filter ---------- */
    function captureFilter() {
        state.filter = {
            post_type: $('seocp-bulk-pt').value,
            status:    $('seocp-bulk-status').value || 'publish',
            q:         $('seocp-bulk-q').value.trim(),
            preset:    (document.querySelector('input[name="seocp-bulk-preset"]:checked') || {}).value || ''
        };
    }

    function find() {
        captureFilter();
        if (!state.filter.post_type) { toast('Pick a post type.', 'warning'); return; }
        // Reset selection state when the filter changes.
        state.selected = {};
        state.allMatching = false;
        state.page = 1;
        loadPage(1).then(function () {
            $('seocp-bulk-count').textContent = fmtNumber(state.total) + ' matching';
            $('seocp-bulk-next-1').disabled = state.total === 0;
        });
    }

    /* ---------- STEP 2: paginated picker ---------- */
    function loadPage(page) {
        var f = state.filter;
        var url = 'posts?post_type=' + encodeURIComponent(f.post_type) +
                  '&status='   + encodeURIComponent(f.status) +
                  '&q='        + encodeURIComponent(f.q) +
                  (f.preset ? '&preset=' + encodeURIComponent(f.preset) : '') +
                  '&page='     + page +
                  '&per_page=' + state.perPage;
        return rest.get(url).then(function (data) {
            state.items = data.items || [];
            state.page = data.page || page;
            state.perPage = data.per_page || state.perPage;
            state.total = data.total || 0;
            state.totalPages = data.total_pages || 1;
            renderResults();
            renderPager();
            renderBulkBar();
            updateSelectionCount();
        }).catch(function (e) { toast(e.message, 'danger'); });
    }

    function renderResults() {
        var host = $('seocp-bulk-results');
        host.setAttribute('data-locked', state.allMatching ? 'true' : 'false');
        host.innerHTML = '';
        if (!state.items.length) {
            host.innerHTML = '<p class="fl-muted">No posts on this page.</p>';
            return;
        }
        state.items.forEach(function (p) {
            var snap = p.snapshot || {};
            var title_state = snap.title_len === 0 ? 'danger' : (snap.title_len >= 50 && snap.title_len <= 60 ? 'success' : 'warning');
            var desc_state  = snap.desc_len  === 0 ? 'danger' : (snap.desc_len  >= 140 && snap.desc_len <= 160 ? 'success' : 'warning');
            var focus_state = snap.has_focus ? 'success' : 'danger';
            var thumb = p.thumb ? ' style="background-image:url(\'' + esc(p.thumb) + '\');"' : '';
            var row = document.createElement('label');
            row.className = 'fl-selectable';
            row.innerHTML =
                '<input type="checkbox" data-id="' + p.id + '" ' + (state.selected[p.id] ? 'checked' : '') + ' style="margin-right:8px;" />' +
                '<div class="fl-selectable__thumb"' + thumb + '></div>' +
                '<div class="fl-selectable__body">' +
                    '<div class="fl-selectable__title">' + esc(p.title || ('#' + p.id)) + '</div>' +
                    '<div class="fl-selectable__meta">' +
                        '<span class="fl-badge fl-badge--' + title_state + '">title ' + snap.title_len + '</span> ' +
                        '<span class="fl-badge fl-badge--' + desc_state  + '">desc '  + snap.desc_len  + '</span> ' +
                        '<span class="fl-badge fl-badge--' + focus_state + '">focus ' + (snap.has_focus ? '✓' : '—') + '</span> ' +
                    '</div>' +
                '</div>';
            row.querySelector('input').addEventListener('change', function (e) {
                state.selected[p.id] = e.target.checked;
                updateSelectionCount();
            });
            host.appendChild(row);
        });
        // Sync the page-level "select all on this page" checkbox.
        $('seocp-bulk-all').checked = state.items.length > 0 &&
            state.items.every(function (p) { return !!state.selected[p.id]; });
    }

    function renderPager() {
        var from = (state.page - 1) * state.perPage + 1;
        var to   = Math.min(state.page * state.perPage, state.total);
        $('seocp-bulk-pager-count').textContent =
            state.total ? (fmtNumber(from) + '–' + fmtNumber(to) + ' of ' + fmtNumber(state.total)) : '0 results';
        $('seocp-bulk-pager-page').value = state.page;
        $('seocp-bulk-pager-page').max = state.totalPages;
        $('seocp-bulk-pager-total').textContent = fmtNumber(state.totalPages);
        $('seocp-bulk-pager-first').disabled = state.page <= 1;
        $('seocp-bulk-pager-prev').disabled  = state.page <= 1;
        $('seocp-bulk-pager-next').disabled  = state.page >= state.totalPages;
        $('seocp-bulk-pager-last').disabled  = state.page >= state.totalPages;
    }

    function renderBulkBar() {
        var bar = $('seocp-bulk-bulk-bar');
        var label = $('seocp-bulk-allmatch-label');
        var cb = $('seocp-bulk-allmatch');
        cb.checked = state.allMatching;
        label.textContent = state.allMatching
            ? ('All ' + fmtNumber(state.total) + ' matching posts queued')
            : ('Select all ' + fmtNumber(state.total) + ' matching posts');
        bar.setAttribute('data-state', state.allMatching ? 'all' : 'page');
    }

    function updateSelectionCount() {
        var n = state.allMatching
            ? state.total
            : Object.keys(state.selected).filter(function (k) { return state.selected[k]; }).length;
        $('seocp-bulk-selcount').textContent = fmtNumber(n) + ' selected';
        $('seocp-bulk-next-2').disabled = n === 0;
    }

    function selectAllOnPage(checked) {
        if (state.allMatching) return; // ignore in all-matching mode
        state.items.forEach(function (p) { state.selected[p.id] = checked; });
        renderResults();
        updateSelectionCount();
    }

    function toggleAllMatching(checked) {
        state.allMatching = checked;
        if (checked) {
            // Wipe individual selection — UI is now driven by the filter spec.
            state.selected = {};
        }
        renderResults();
        renderBulkBar();
        updateSelectionCount();
    }

    /* ---------- STEP 3: template + fields (unchanged) ---------- */
    function renderTemplates() {
        var applicable = state.templates.filter(function (t) {
            return !t.applies_to_post_types.length || t.applies_to_post_types.indexOf(state.filter.post_type) >= 0;
        });
        var host = $('seocp-bulk-tpl-list');
        host.innerHTML = '';
        if (!applicable.length) { host.innerHTML = '<p class="fl-muted">No templates apply to this post type.</p>'; return; }
        applicable.forEach(function (t) {
            var row = document.createElement('label');
            row.className = 'fl-selectable';
            row.innerHTML =
                '<input type="radio" name="seocp-bulk-tpl-radio" value="' + t.id + '" style="margin-right:8px;" />' +
                '<div class="fl-selectable__body">' +
                    '<div class="fl-selectable__title">' + esc(t.name) + '</div>' +
                    '<div class="fl-selectable__meta">' + esc(t.description || '') + ' • <strong>' + t.produces.length + '</strong> field(s)</div>' +
                '</div>';
            row.querySelector('input').addEventListener('change', function () {
                state.template = t;
                document.querySelectorAll('#seocp-bulk-tpl-list .fl-selectable').forEach(function (n) { n.setAttribute('aria-selected', 'false'); });
                row.setAttribute('aria-selected', 'true');
                renderFields();
            });
            host.appendChild(row);
        });
        var first = host.querySelector('input[type="radio"]');
        if (first) { first.checked = true; first.dispatchEvent(new Event('change')); }
    }

    function loadFields() {
        return rest.get('fields/' + encodeURIComponent(state.filter.post_type)).then(function (data) {
            state.fields = [];
            (data.groups || []).forEach(function (g) { g.fields.forEach(function (f) { state.fields.push(f); }); });
            state.defaults = data.defaults || [];
        });
    }

    function renderFields() {
        if (!state.template) return;
        var produces = state.template.produces || [];
        var allowed = state.fields.filter(function (f) { return !produces.length || produces.indexOf(f.id) >= 0; });
        var host = $('seocp-bulk-fields');
        host.innerHTML = '';
        state.picked = {};
        allowed.forEach(function (f) {
            var checked = state.defaults.length ? state.defaults.indexOf(f.id) >= 0 : true;
            state.picked[f.id] = checked;
            var lbl = document.createElement('label');
            lbl.className = 'fl-choice';
            lbl.title = f.description || '';
            lbl.innerHTML =
                '<input type="checkbox" data-fid="' + esc(f.id) + '" ' + (checked ? 'checked' : '') + ' />' +
                '<span><strong>' + esc(f.label) + '</strong> <span class="fl-muted">(' + esc(f.id) + ')</span></span>';
            lbl.querySelector('input').addEventListener('change', function (e) { state.picked[f.id] = e.target.checked; updateConfigState(); });
            host.appendChild(lbl);
        });
        updateConfigState();
    }

    function updateConfigState() {
        var n = Object.keys(state.picked).filter(function (k) { return state.picked[k]; }).length;
        $('seocp-bulk-next-3').disabled = n === 0;
    }

    /* ---------- STEP 4: confirm ---------- */
    function selectedCount() {
        return state.allMatching
            ? state.total
            : Object.keys(state.selected).filter(function (k) { return state.selected[k]; }).length;
    }

    function renderConfirm() {
        var posts  = selectedCount();
        var fields = Object.keys(state.picked).filter(function (k) { return state.picked[k]; }).length;
        var calls  = posts;
        var costEst = posts * Math.max(0.001, fields * 0.0008);
        // OpenAI's Batch API is 50% cheaper on tokens.
        if (state.dispatch === 'batch') costEst = costEst * 0.5;
        $('seocp-bulk-kpi-posts').textContent  = fmtNumber(posts);
        $('seocp-bulk-kpi-fields').textContent = fields;
        $('seocp-bulk-kpi-calls').textContent  = fmtNumber(calls);
        $('seocp-bulk-kpi-cost').textContent   = '$' + (costEst >= 100 ? costEst.toFixed(0) : costEst.toFixed(2));
    }

    function enqueue() {
        var fields = Object.keys(state.picked).filter(function (k) { return state.picked[k]; });
        if (!fields.length) { toast('No fields ticked.', 'warning'); return; }
        var template_id = state.template ? state.template.id : 0;
        if (!template_id) { toast('Pick a template.', 'warning'); return; }

        var btn = $('seocp-bulk-enqueue');
        btn.disabled = true;
        btn.innerHTML = '<span class="fl-spinner" style="margin-right:6px;"></span>Queuing…';

        var payload;
        if (state.allMatching) {
            payload = {
                select_all_matching: true,
                filter: state.filter,
                template_id: template_id,
                fields: fields,
                mode: state.mode,
                dispatch: state.dispatch
            };
        } else {
            var post_ids = Object.keys(state.selected)
                .filter(function (k) { return state.selected[k]; })
                .map(function (n) { return parseInt(n, 10); });
            if (!post_ids.length) { btn.disabled = false; btn.textContent = 'Queue batch'; toast('No posts selected.', 'warning'); return; }
            payload = { post_ids: post_ids, template_id: template_id, fields: fields, mode: state.mode, dispatch: state.dispatch };
        }

        rest.post('bulk', payload)
            .then(function (r) {
                state.batch_id = r.batch_id;
                $('seocp-bulk-progress-card').hidden = false;
                btn.textContent = '✓ Queued ' + fmtNumber(r.count) + (r.truncated ? ' (capped)' : '');
                if (r.truncated) {
                    toast('Capped at ' + fmtNumber(r.count) + ' jobs — narrow your filter to enqueue more.', 'warning');
                } else {
                    toast('Queued ' + fmtNumber(r.count) + ' jobs.', 'success');
                }
                // Kick a tick immediately so the user sees movement within seconds.
                tickAndPoll();
                // Add the new batch to the Recent batches panel.
                loadRecentBatches();
            })
            .catch(function (e) {
                btn.disabled = false;
                btn.textContent = 'Queue batch';
                toast(e.message, 'danger');
            });
    }

    /**
     * Drives the queue from the browser: every poll cycle posts to /bulk/tick
     * which drains a few jobs synchronously, then renders progress. This makes
     * batches advance even when WP-Cron is disabled or the site has no traffic.
     */
    function tickAndPoll() {
        if (!state.batch_id) return;
        rest.post('bulk/tick', { batch_id: state.batch_id, limit: 3 })
            .then(function (data) {
                renderProgress(data.progress || {});
                schedulePoll(data.progress || {});
            })
            .catch(function () {
                // Tick can fail (auth, network) — fall back to a passive progress poll.
                pollOnly();
            });
    }

    function pollOnly() {
        if (!state.batch_id) return;
        rest.get('runs?batch_id=' + encodeURIComponent(state.batch_id) + '&limit=1').then(function (data) {
            renderProgress(data.progress || {});
            schedulePoll(data.progress || {});
        });
    }

    function schedulePoll(p) {
        var done = (p.completed || 0) + (p.failed || 0) + (p.cancelled || 0);
        if (p.total && done < p.total) {
            // Batch mode advances every cron tick, so back off polling a bit.
            var delay = (p.dispatch === 'batch') ? 15000 : 4000;
            state.pollTimer = setTimeout(tickAndPoll, delay);
        }
    }

    function renderBatchChunkSummary(chunks) {
        if (!chunks || !chunks.length) return '';
        var pills = chunks.map(function (c) {
            var s = (c.status || '').toLowerCase();
            var kind = 'fl-badge';
            if (s === 'completed') kind = 'fl-badge fl-badge--success';
            else if (s === 'failed' || s === 'expired' || s === 'cancelled') kind = 'fl-badge fl-badge--danger';
            else if (s === 'in_progress' || s === 'finalizing' || s === 'validating' || s === 'submitted') kind = 'fl-badge fl-badge--info';
            var n = (c.completed_count || 0) + '/' + (c.request_count || 0);
            return '<span class="' + kind + '" title="' + esc(c.openai_batch_id || '') + '">' + esc(s) + ' ' + n + '</span>';
        }).join(' ');
        return '<div class="fl-row" style="flex-wrap:wrap;margin-top:8px;gap:6px;">' +
            '<span class="fl-muted fl-text-200">OpenAI chunks:</span> ' + pills + '</div>';
    }

    function renderProgress(p) {
        var completed = p.completed || 0;
        var failed = p.failed || 0;
        var cancelled = p.cancelled || 0;
        var pending = p.pending || 0;
        var running = p.running || 0;
        var submitted = p.submitted || 0;
        var ready = p.ready || 0;
        var applying = p.applying || 0;
        var isBatch = p.dispatch === 'batch';
        var done = completed + failed + cancelled;
        var pct = p.total ? Math.round(100 * done / p.total) : 0;
        var isComplete = p.total > 0 && done >= p.total;
        var hasPending = pending > 0 || running > 0 || submitted > 0 || ready > 0 || applying > 0;
        var host = $('seocp-bulk-progress');

        // Real-write outcome from the runs table — separates "wrote ≥1 field"
        // from "completed but wrote nothing" (e.g. picker had inactive plugin's
        // fields, or AI returned empty strings).
        var w = p.writes || {};
        var wroteFields = w.applied || 0;
        var noOp        = w.noop    || 0;
        var writeFailed = w.failed  || 0;

        var stopBtn = (hasPending && state.batch_id)
            ? '<button type="button" class="fl-button fl-button--small fl-button--danger" id="seocp-bulk-stop">Stop & cancel pending</button>'
            : '';

        var noopWarning = (state.mode !== 'review' && completed > 0 && noOp > 0 && wroteFields === 0)
            ? '<div class="fl-message-bar fl-message-bar--warning" style="margin-top:8px;">' +
                '<div><strong>⚠ Worker finished posts but wrote nothing.</strong> ' +
                fmtNumber(noOp) + ' post' + (noOp === 1 ? '' : 's') + ' had no fields written. ' +
                'Common cause: the picker included fields whose SEO plugin (Rank Math / Yoast / AIOSEO) isn\'t active, or the product hard-guard stripped every selected field. ' +
                'Open <a href="' + esc(window.seocpData.adminUrl + '?page=seo-copilot-settings&tab=logs') + '">Settings → Logs &amp; Diagnostics</a> to see exactly which fields the runs reported.</div>' +
            '</div>'
            : '';

        var html =
            '<div class="fl-row" style="margin-bottom:4px;">' +
                '<strong>' + fmtNumber(done) + ' / ' + fmtNumber(p.total || 0) + '</strong>' +
                '<span class="fl-spacer"></span>' +
                '<span class="fl-muted">' + pct + '%</span>' +
                (stopBtn ? '<span style="margin-left:12px;">' + stopBtn + '</span>' : '') +
            '</div>' +
            '<div class="fl-progress" style="margin:8px 0;"><div class="fl-progress__fill" style="width:' + pct + '%"></div></div>' +
            '<div class="fl-row" style="flex-wrap:wrap;">' +
                '<span class="fl-badge">pending ' + fmtNumber(pending) + '</span> ' +
                (isBatch ? '<span class="fl-badge fl-badge--info">submitted ' + fmtNumber(submitted) + '</span> ' : '') +
                (isBatch ? '<span class="fl-badge fl-badge--info">ready ' + fmtNumber(ready) + '</span> ' : '') +
                (isBatch ? '<span class="fl-badge fl-badge--info">applying ' + fmtNumber(applying) + '</span> ' : '') +
                (!isBatch ? '<span class="fl-badge fl-badge--info">running ' + fmtNumber(running) + '</span> ' : '') +
                '<span class="fl-badge">completed ' + fmtNumber(completed) + '</span> ' +
                '<span class="fl-badge fl-badge--danger">failed ' + fmtNumber(failed) + '</span> ' +
                (cancelled > 0 ? '<span class="fl-badge fl-badge--warning">cancelled ' + fmtNumber(cancelled) + '</span> ' : '') +
            '</div>' +
            (isBatch ? renderBatchChunkSummary(p.openai_chunks || []) : '') +
            // Real write outcome — what actually changed in the database.
            (state.mode === 'review'
                ? '<div class="fl-row" style="flex-wrap:wrap;margin-top:6px;">' +
                    '<span class="fl-muted fl-text-200">Generated for review:</span> ' +
                    '<span class="fl-badge fl-badge--info">proposals saved ' + fmtNumber(w.proposed || 0) + '</span>' +
                  '</div>'
                : '<div class="fl-row" style="flex-wrap:wrap;margin-top:6px;">' +
                    '<span class="fl-muted fl-text-200">Wrote to DB:</span> ' +
                    '<span class="fl-badge fl-badge--success">wrote fields ' + fmtNumber(wroteFields) + '</span> ' +
                    (noOp > 0 ? '<span class="fl-badge fl-badge--warning">no-op ' + fmtNumber(noOp) + '</span> ' : '') +
                    (writeFailed > 0 ? '<span class="fl-badge fl-badge--danger">write failed ' + fmtNumber(writeFailed) + '</span>' : '') +
                  '</div>'
            ) +
            noopWarning;

        if (isComplete) {
            var logsUrl   = window.seocpData.adminUrl + '?page=seo-copilot-settings&tab=logs';
            var reviewUrl = window.seocpData.adminUrl + '?page=seo-copilot-pending-review';
            var inReviewMode = state.mode === 'review';
            var summary;
            var bar_kind = 'success';

            if (inReviewMode) {
                summary = '<strong>✓ Generation finished.</strong> ' +
                    fmtNumber(w.proposed || completed) + ' proposal' + ((w.proposed || completed) === 1 ? '' : 's') + ' saved' +
                    (failed > 0 ? ', ' + fmtNumber(failed) + ' failed' : '') +
                    (cancelled > 0 ? ', ' + fmtNumber(cancelled) + ' cancelled' : '') +
                    '. Nothing has been written yet — open Pending Review to approve and apply.';
            } else if (wroteFields === 0 && completed > 0) {
                bar_kind = 'warning';
                summary = '<strong>⚠ Batch finished but no fields were written.</strong> ' +
                    fmtNumber(completed) + ' post' + (completed === 1 ? '' : 's') + ' processed, ' +
                    fmtNumber(noOp) + ' no-op (worker ran but nothing landed in the database). ' +
                    'Check Logs for per-post details — the most common cause is picking fields whose SEO plugin isn\'t active.';
            } else {
                summary = '<strong>✓ Batch finished.</strong> Wrote fields to ' +
                    fmtNumber(wroteFields) + ' post' + (wroteFields === 1 ? '' : 's') +
                    (noOp > 0 ? ' (' + fmtNumber(noOp) + ' no-op)' : '') +
                    (failed > 0 ? ', ' + fmtNumber(failed) + ' failed' : '') +
                    (cancelled > 0 ? ', ' + fmtNumber(cancelled) + ' cancelled' : '') +
                    (isBatch ? ' via OpenAI Batch API (50% token discount applied)' : '') +
                    '. Applied changes are saved — nothing is rolled back.';
            }

            var hint = inReviewMode
                ? ''
                : '<div class="fl-muted fl-text-200">Where to verify: Edit a post → SEO plugin metabox at the bottom · or View Source on the front-end and look at the &lt;title&gt; / &lt;meta name=&quot;description&quot;&gt; tags · or open Logs for the exact field IDs each run wrote.</div>';

            // Rank Math's "SEO Details" column shows N/A after bulk because RM
            // computes its score in editor JS, not on direct postmeta writes.
            // Surface this so users don't think the writes failed.
            if (!inReviewMode && (window.seocpData && window.seocpData.seoPlugin) === 'rank_math' && wroteFields > 0) {
                hint += '<div class="fl-message-bar fl-message-bar--info" style="margin-top:8px;">' +
                    '<div><strong>About Rank Math\'s "SEO Details" column:</strong> Rank Math computes its score in the post-editor JavaScript, not when meta is written from outside. After this bulk run the column will show <code>N/A</code> until you either ' +
                    '(a) open each product (its score recomputes on editor load) or ' +
                    '(b) run <strong>Rank Math → Status &amp; Tools → Database Tools → Recalculate SEO Scores</strong>. ' +
                    'The actual SEO Title, Meta Description and Focus Keyword <strong>are</strong> already saved — verify in the Rank Math metabox at the bottom of the editor.</div>' +
                '</div>';
            }

            html += '<div class="fl-message-bar fl-message-bar--' + bar_kind + '" style="margin-top:12px;">' +
                '<div style="display:flex;flex-direction:column;gap:6px;width:100%;">' +
                    '<div>' + summary + '</div>' +
                    hint +
                    '<div class="fl-row" style="gap:8px;">' +
                        (inReviewMode
                            ? '<a class="fl-button fl-button--small fl-button--primary" href="' + esc(reviewUrl) + '">Open Pending Review →</a>'
                            : '<a class="fl-button fl-button--small" href="' + esc(logsUrl) + '">View runs in Logs →</a>') +
                    '</div>' +
                '</div>' +
            '</div>';
        } else if (isBatch) {
            html += '<div class="fl-muted fl-text-200" style="margin-top:8px;">Submitted to OpenAI Batch API — results return within 24h (often much sooner) and apply automatically as they download. You can close this page; come back anytime to check progress.</div>';
        } else if (state.mode === 'review') {
            html += '<div class="fl-muted fl-text-200" style="margin-top:8px;">Generating proposals — saved to Pending Review as each post finishes. Nothing is written yet.</div>';
        } else {
            html += '<div class="fl-muted fl-text-200" style="margin-top:8px;">Each post is generated then written immediately — no separate review step. Stopping cancels pending jobs only; already-applied changes are kept.</div>';
        }
        host.innerHTML = html;

        var stopEl = document.getElementById('seocp-bulk-stop');
        if (stopEl) {
            stopEl.addEventListener('click', function () { stopBatch(state.batch_id); });
        }
    }

    function setRecentCollapsed(collapsed) {
        var card = $('seocp-bulk-recent-card');
        var toggle = $('seocp-bulk-recent-toggle');
        if (!card) return;
        card.setAttribute('data-collapsed', collapsed ? 'true' : 'false');
        if (toggle) toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    function toggleRecentCollapsed() {
        var card = $('seocp-bulk-recent-card');
        if (!card) return;
        var nowCollapsed = card.getAttribute('data-collapsed') !== 'true';
        setRecentCollapsed(nowCollapsed);
        try { sessionStorage.setItem('seocp_recent_collapsed', nowCollapsed ? 'true' : 'false'); } catch (_) {}
    }

    function stopBatch(batch_id) {
        if (!batch_id) return;
        if (!confirm('Stop this batch?\n\n• Posts already updated stay updated (no rollback).\n• Pending jobs are cancelled (no AI call, no changes).\n• Jobs currently running will finish their current post.')) return;
        rest.post('bulk/stop', { batch_id: batch_id })
            .then(function (r) {
                toast('Cancelled ' + fmtNumber(r.cancelled || 0) + ' pending job' + ((r.cancelled || 0) === 1 ? '' : 's') + '.', 'success');
                if (r.progress) renderProgress(r.progress);
                if (state.pollTimer) { clearTimeout(state.pollTimer); state.pollTimer = null; }
                // Keep polling briefly to catch any in-flight running jobs that finish.
                setTimeout(tickAndPoll, 1500);
                loadRecentBatches();
            })
            .catch(function (e) { toast(e.message, 'danger'); });
    }

    /* ---------- Recent batches panel (lives at the bottom, collapsed by default) ---------- */
    function loadRecentBatches() {
        rest.get('batches?limit=10').then(function (data) {
            var batches = (data && data.batches) || [];
            var card = $('seocp-bulk-recent-card');
            var body = $('seocp-bulk-recent-body');
            var countBadge = $('seocp-bulk-recent-count');
            if (!batches.length) {
                card.hidden = true;
                return;
            }
            card.hidden = false;
            if (countBadge) countBadge.textContent = batches.length + (batches.length === 1 ? ' batch' : ' batches');

            // Auto-expand the panel if there's an active batch (pending or running).
            // User-initiated toggles are persisted in sessionStorage and respected
            // unless an active batch overrides.
            var hasActive = batches.some(function (b) {
                return (b.pending || 0) > 0 || (b.running || 0) > 0 ||
                       (b.submitted || 0) > 0 || (b.ready || 0) > 0 || (b.applying || 0) > 0;
            });
            var userPref = null;
            try { userPref = sessionStorage.getItem('seocp_recent_collapsed'); } catch (_) {}
            var shouldCollapse = hasActive ? false : (userPref === null ? true : userPref === 'true');
            setRecentCollapsed(shouldCollapse);

            body.innerHTML = '';
            batches.forEach(function (b) {
                var completed = b.completed || 0;
                var failed = b.failed || 0;
                var cancelled = b.cancelled || 0;
                var pending = b.pending || 0;
                var running = b.running || 0;
                var submitted = b.submitted || 0;
                var ready = b.ready || 0;
                var applying = b.applying || 0;
                var inFlight = pending + running + submitted + ready + applying;
                var done = completed + failed + cancelled;
                var pct = b.total ? Math.round(100 * done / b.total) : 0;
                var isActive = inFlight > 0;
                var dispatchBadge = (b.dispatch === 'batch')
                    ? ' <span class="fl-badge fl-badge--info" title="OpenAI Batch API — 50% off">batch</span>'
                    : '';
                var isComplete = b.total > 0 && done >= b.total;
                var stateBadge;
                if (isActive)              stateBadge = '<span class="fl-badge fl-badge--info">Running</span>';
                else if (cancelled > 0 && completed === 0) stateBadge = '<span class="fl-badge fl-badge--warning">Cancelled</span>';
                else if (cancelled > 0)    stateBadge = '<span class="fl-badge fl-badge--success">✓ Stopped (' + fmtNumber(completed) + ' applied)</span>';
                else if (isComplete)       stateBadge = '<span class="fl-badge fl-badge--success">✓ Complete</span>';
                else                       stateBadge = '<span class="fl-badge">Queued</span>';

                var row = document.createElement('div');
                row.className = 'fl-card';
                row.style.padding = '14px 16px';
                row.innerHTML =
                    '<div class="fl-row" style="margin-bottom:6px;">' +
                        '<strong>' + esc(b.started_at || '—') + '</strong> ' +
                        '<span class="fl-mono fl-muted" style="font-size:11px;">' + esc((b.batch_id || '').slice(0, 8)) + '</span>' +
                        dispatchBadge +
                        '<span class="fl-spacer"></span>' +
                        stateBadge +
                    '</div>' +
                    '<div class="fl-progress" style="margin:4px 0;"><div class="fl-progress__fill" style="width:' + pct + '%"></div></div>' +
                    '<div class="fl-row fl-text-200" style="flex-wrap:wrap;">' +
                        '<span>' + fmtNumber(done) + ' / ' + fmtNumber(b.total || 0) + ' processed</span>' +
                        '<span class="fl-muted">•</span>' +
                        '<span class="fl-badge fl-badge--success">applied ' + fmtNumber(completed) + '</span>' +
                        '<span class="fl-badge fl-badge--danger">failed ' + fmtNumber(failed) + '</span>' +
                        '<span class="fl-badge">pending ' + fmtNumber(pending) + '</span>' +
                        (cancelled > 0 ? '<span class="fl-badge fl-badge--warning">cancelled ' + fmtNumber(cancelled) + '</span>' : '') +
                        '<span class="fl-spacer"></span>' +
                        (isActive
                            ? '<button type="button" class="fl-button fl-button--small fl-button--danger" data-batch-id="' + esc(b.batch_id) + '" data-action="stop">Stop</button> '
                            : '') +
                        '<a class="fl-button fl-button--small" data-batch-id="' + esc(b.batch_id) + '" href="#" data-action="resume">Show progress</a>' +
                    '</div>';
                body.appendChild(row);
            });
            // Wire up "Show progress" + "Stop" buttons.
            body.querySelectorAll('a[data-action="resume"]').forEach(function (a) {
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    state.batch_id = a.getAttribute('data-batch-id');
                    $('seocp-bulk-progress-card').hidden = false;
                    gotoStep(3);
                    tickAndPoll();
                });
            });
            body.querySelectorAll('button[data-action="stop"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    stopBatch(btn.getAttribute('data-batch-id'));
                });
            });
        }).catch(function () {
            // Silent — recent batches is a nice-to-have.
        });
    }

    /* ---------- bootstrap ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        var root = $('seocp-bulk');
        if (!root) return;
        try { state.templates = JSON.parse(root.getAttribute('data-templates') || '[]'); }
        catch (_) { state.templates = []; }

        widgets.stepperJumps(root, function (i) { gotoStep(i); });

        // Step 1
        $('seocp-bulk-pt').addEventListener('change', function () {
            $('seocp-bulk-next-1').disabled = true;
            $('seocp-bulk-count').textContent = '';
        });
        $('seocp-bulk-search').addEventListener('click', find);
        $('seocp-bulk-next-1').addEventListener('click', function () {
            // Re-render in case results changed.
            renderResults();
            renderPager();
            renderBulkBar();
            updateSelectionCount();
            gotoStep(1);
        });
        $('seocp-bulk-back-1').addEventListener('click', function () { gotoStep(0); });

        // Step 2
        $('seocp-bulk-allmatch').addEventListener('change', function (e) { toggleAllMatching(e.target.checked); });
        $('seocp-bulk-all').addEventListener('change', function (e) { selectAllOnPage(e.target.checked); });
        $('seocp-bulk-perpage').addEventListener('change', function (e) {
            state.perPage = parseInt(e.target.value, 10) || 20;
            state.page = 1;
            loadPage(1);
        });
        $('seocp-bulk-pager-first').addEventListener('click', function () { if (state.page > 1) loadPage(1); });
        $('seocp-bulk-pager-prev').addEventListener('click',  function () { if (state.page > 1) loadPage(state.page - 1); });
        $('seocp-bulk-pager-next').addEventListener('click',  function () { if (state.page < state.totalPages) loadPage(state.page + 1); });
        $('seocp-bulk-pager-last').addEventListener('click',  function () { if (state.page < state.totalPages) loadPage(state.totalPages); });
        $('seocp-bulk-pager-page').addEventListener('change', function (e) {
            var p = Math.max(1, Math.min(state.totalPages, parseInt(e.target.value, 10) || 1));
            loadPage(p);
        });

        $('seocp-bulk-next-2').addEventListener('click', function () {
            loadFields().then(function () { renderTemplates(); gotoStep(2); }).catch(function (e) { toast(e.message, 'danger'); });
        });
        $('seocp-bulk-back-2').addEventListener('click', function () { gotoStep(1); });

        $('seocp-bulk-next-3').addEventListener('click', function () { renderConfirm(); gotoStep(3); });
        $('seocp-bulk-back-3').addEventListener('click', function () { gotoStep(2); });

        $('seocp-bulk-enqueue').addEventListener('click', enqueue);

        // Mode toggle (auto-apply vs review).
        document.querySelectorAll('input[name="seocp-bulk-mode"]').forEach(function (radio) {
            radio.addEventListener('change', function (e) {
                state.mode = e.target.value === 'review' ? 'review' : 'apply';
                document.querySelectorAll('label[data-mode-option]').forEach(function (lbl) {
                    lbl.setAttribute('aria-selected', String(lbl.getAttribute('data-mode-option') === state.mode));
                });
            });
        });

        // Dispatch toggle (synchronous vs OpenAI Batch API).
        document.querySelectorAll('input[name="seocp-bulk-dispatch"]').forEach(function (radio) {
            radio.addEventListener('change', function (e) {
                state.dispatch = e.target.value === 'batch' ? 'batch' : 'sync';
                document.querySelectorAll('label[data-dispatch-option]').forEach(function (lbl) {
                    lbl.setAttribute('aria-selected', String(lbl.getAttribute('data-dispatch-option') === state.dispatch));
                });
                // Refresh cost estimate — Batch is 50% off.
                renderConfirm();
            });
        });

        // Recent batches panel — load on entry, refresh button, and toggle handler.
        loadRecentBatches();
        var refreshBtn = $('seocp-bulk-recent-refresh');
        if (refreshBtn) refreshBtn.addEventListener('click', loadRecentBatches);
        var toggleBtn = $('seocp-bulk-recent-toggle');
        if (toggleBtn) toggleBtn.addEventListener('click', toggleRecentCollapsed);
    });
})();
