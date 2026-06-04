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
    function budget(fid) { return LEN_BUDGET[fid] || { min: 0, max: 200 }; }

    var state = {
        batch_id: '',
        page: 1,
        perPage: 20,
        posts: [],          // current page
        total: 0,           // total pending segments (matching filter)
        // Per-segment editable values + checked flags. Keys: segment id (string).
        edited: {},
        checked: {}
    };

    function $(id) { return document.getElementById(id); }
    function fmtNumber(n) { return (n || 0).toLocaleString(); }

    function load() {
        var offset = (state.page - 1) * state.perPage * 9; // ~9 segments per post worst case
        var qs = 'segments?limit=' + (state.perPage * 9) +
                 (state.batch_id ? '&batch_id=' + encodeURIComponent(state.batch_id) : '');
        rest.get(qs).then(function (data) {
            state.posts = (data.posts || []).slice(0, state.perPage);
            state.total = data.total || 0;
            // Wipe edited/checked maps not in current view to avoid stale state.
            state.edited = {};
            state.checked = {};
            state.posts.forEach(function (p) {
                p.segments.forEach(function (s) {
                    state.checked[s.id] = true; // default-tick everything
                });
            });
            render();
            updatePending(state.total);
        }).catch(function (e) { toast(e.message, 'danger'); });
    }

    function updatePending(n) {
        var el = $('seocp-review-pending-count');
        if (el) el.textContent = fmtNumber(n) + ' pending';
        var info = $('seocp-review-pageinfo');
        if (info) {
            var from = Math.min(state.total, (state.page - 1) * state.perPage + 1);
            var to   = Math.min(state.total, from + state.posts.length - 1);
            info.textContent = state.total
                ? ('Showing ' + fmtNumber(state.posts.length) + ' post(s) — ' + fmtNumber(state.total) + ' segment(s) pending')
                : 'No pending segments.';
        }
    }

    function render() {
        var host = $('seocp-review-list');
        host.innerHTML = '';
        if (!state.posts.length) {
            host.innerHTML = '<div class="fl-card"><p class="fl-muted">Nothing pending in this filter.</p></div>';
            return;
        }
        state.posts.forEach(function (p) {
            var card = document.createElement('div');
            card.className = 'fl-card';
            card.setAttribute('data-post-id', p.post_id);

            var thumb = p.thumb ? ' style="background-image:url(\'' + esc(p.thumb) + '\');background-size:cover;background-position:center;"' : '';
            var titleHtml =
                '<div class="fl-row" style="margin-bottom:12px;">' +
                    '<div class="fl-selectable__thumb"' + thumb + '></div>' +
                    '<div style="flex:1;min-width:0;">' +
                        '<div class="fl-selectable__title">' + esc(p.title) + ' <span class="fl-muted fl-text-200">#' + p.post_id + '</span></div>' +
                        '<div class="fl-selectable__meta">' +
                            esc(p.post_type) + ' • <span class="fl-mono fl-text-200">' + esc((p.batch_id || '').slice(0, 8)) + '</span>' +
                            (p.permalink ? ' • <a href="' + esc(p.permalink) + '" target="_blank" rel="noopener">View ↗</a>' : '') +
                            (p.edit_url ? ' • <a href="' + esc(p.edit_url) + '" target="_blank" rel="noopener">Edit ↗</a>' : '') +
                        '</div>' +
                    '</div>' +
                    '<div class="fl-row" style="gap:6px;">' +
                        '<button type="button" class="fl-button fl-button--small fl-button--primary" data-action="apply-post" data-post-id="' + p.post_id + '">Apply this post</button>' +
                        '<button type="button" class="fl-button fl-button--small" data-action="reject-post" data-post-id="' + p.post_id + '">Reject</button>' +
                    '</div>' +
                '</div>';
            card.innerHTML = titleHtml;

            // SERP preview slot.
            var serpHost = document.createElement('div');
            serpHost.style.marginBottom = '12px';
            card.appendChild(serpHost);

            // Segments.
            var titleSeg = p.segments.find(function (s) {
                return ['rm_seo_title', 'yoast_seo_title', 'aioseo_title', 'seopress_seo_title'].indexOf(s.field_id) >= 0;
            });
            var descSeg = p.segments.find(function (s) {
                return ['rm_meta_description', 'yoast_meta_description', 'aioseo_description', 'seopress_meta_description'].indexOf(s.field_id) >= 0;
            });
            var serp = (titleSeg || descSeg) ? widgets.serp(serpHost, {
                url: p.permalink,
                title: titleSeg ? titleSeg.generated_value : p.title,
                description: descSeg ? descSeg.generated_value : ''
            }) : null;

            p.segments.forEach(function (s) {
                var b = budget(s.field_id);
                var initial = s.generated_value || '';
                state.edited[s.id] = initial;

                var seg = document.createElement('div');
                seg.className = 'seocp-fieldblock';
                seg.style.marginBottom = '8px';
                seg.innerHTML =
                    '<div class="seocp-fieldblock__head">' +
                        '<label class="fl-choice"><input type="checkbox" class="js-seg-check" data-id="' + s.id + '" checked />' +
                            '<span><strong>' + esc(s.label || s.field_id) + '</strong> <span class="fl-muted fl-text-200">(' + esc(s.field_id) + ')</span></span></label>' +
                        '<span class="fl-spacer"></span>' +
                        '<span class="fl-muted fl-text-200">target ' + b.min + '–' + b.max + ' chars</span>' +
                    '</div>' +
                    '<div class="seocp-diff">' +
                        '<div class="seocp-diff__col"><h4>Current</h4><pre class="seocp-diff__current">' + esc(s.current_value || '—') + '</pre></div>' +
                        '<div class="seocp-diff__col"><h4>Proposed (editable)</h4>' +
                            '<textarea class="fl-textarea js-seg-value" data-id="' + s.id + '" rows="3">' + esc(initial) + '</textarea>' +
                            '<span class="fl-counter" data-counter="' + s.id + '"></span>' +
                        '</div>' +
                    '</div>';
                card.appendChild(seg);

                var counter = widgets.counter(seg.querySelector('[data-counter="' + s.id + '"]'), b.min, b.max);
                counter.update(initial);

                seg.querySelector('textarea.js-seg-value').addEventListener('input', function (e) {
                    var v = e.target.value;
                    state.edited[s.id] = v;
                    counter.update(v);
                    if (serp) {
                        if (titleSeg && s.id === titleSeg.id) serp.update({ title: v });
                        if (descSeg  && s.id === descSeg.id)  serp.update({ description: v });
                    }
                });
                seg.querySelector('input.js-seg-check').addEventListener('change', function (e) {
                    state.checked[s.id] = e.target.checked;
                });
            });

            host.appendChild(card);

            // Per-card "Apply this post" / "Reject" handlers.
            card.querySelector('button[data-action="apply-post"]').addEventListener('click', function () {
                applyForPost(p.post_id);
            });
            card.querySelector('button[data-action="reject-post"]').addEventListener('click', function () {
                rejectForPost(p);
            });
        });
    }

    function collectItemsForPost(post_id) {
        var items = [];
        var post = state.posts.find(function (p) { return p.post_id === post_id; });
        if (!post) return items;
        post.segments.forEach(function (s) {
            if (state.checked[s.id]) {
                items.push({ id: s.id, value: state.edited[s.id] });
            }
        });
        return items;
    }

    function applyForPost(post_id) {
        var items = collectItemsForPost(post_id);
        if (!items.length) { toast('Nothing ticked for this post.', 'warning'); return; }
        rest.post('segments/apply', { items: items })
            .then(function (r) {
                showStatus('success', '✓ Applied ' + r.applied + ' field(s) to post #' + post_id +
                    (r.skipped > 0 ? ' (' + r.skipped + ' skipped)' : ''));
                // Remove the applied card and any segments that landed.
                state.total -= r.applied || 0;
                load();
            })
            .catch(function (e) { showStatus('danger', e.message); });
    }

    function rejectForPost(post) {
        if (!confirm('Reject all ' + post.segments.length + ' proposal(s) for "' + post.title + '"? They will be discarded.')) return;
        var ids = post.segments.map(function (s) { return s.id; });
        rest.post('segments/reject', { ids: ids })
            .then(function (r) {
                showStatus('success', 'Rejected ' + r.rejected + ' proposal(s).');
                state.total -= r.rejected || 0;
                load();
            })
            .catch(function (e) { showStatus('danger', e.message); });
    }

    function applyVisible() {
        var items = [];
        state.posts.forEach(function (p) {
            p.segments.forEach(function (s) {
                if (state.checked[s.id]) items.push({ id: s.id, value: state.edited[s.id] });
            });
        });
        if (!items.length) { toast('Nothing ticked.', 'warning'); return; }
        var btn = $('seocp-review-apply-visible');
        btn.disabled = true;
        btn.innerHTML = '<span class="fl-spinner" style="margin-right:6px;"></span>Applying…';
        rest.post('segments/apply', { items: items })
            .then(function (r) {
                btn.disabled = false;
                btn.textContent = 'Apply all visible';
                showStatus('success', '✓ Applied ' + r.applied + ' field(s) across ' +
                    state.posts.length + ' post(s)' +
                    (r.skipped > 0 ? ' (' + r.skipped + ' skipped — no edit permission or constraint failed)' : '') + '.');
                load();
            })
            .catch(function (e) {
                btn.disabled = false;
                btn.textContent = 'Apply all visible';
                showStatus('danger', e.message);
            });
    }

    /**
     * Drain one batch by repeatedly POSTing segments/apply until the server
     * reports `remaining === 0` (or a chunk makes no progress). The server
     * caps each call at ~500 segments, so a 150k-segment batch needs many
     * round-trips — this loop handles them so one click really does apply all.
     */
    function drainBatch(batch_id, acc, onProgress) {
        return rest.post('segments/apply', { batch_id: batch_id }).then(function (r) {
            acc.applied += r.applied || 0;
            acc.skipped += r.skipped || 0;
            if (onProgress) onProgress(acc, r.remaining);
            var madeProgress = (r.applied || 0) > 0 || (r.skipped || 0) > 0;
            var remaining = (typeof r.remaining === 'number') ? r.remaining : 0;
            if (remaining > 0 && madeProgress) {
                return drainBatch(batch_id, acc, onProgress); // keep draining
            }
            return acc;
        });
    }

    function applyEntireBatch() {
        if (!confirm('Apply EVERY pending proposal' + (state.batch_id ? ' in this batch' : ' across all batches') + '?\n\nThis writes to all matching posts. Large batches are applied in chunks and may take a minute. Cannot be undone.')) return;

        var btn = $('seocp-review-apply-batch');
        btn.disabled = true;
        btn.innerHTML = '<span class="fl-spinner" style="margin-right:6px;"></span>Applying…';
        var acc = { applied: 0, skipped: 0 };
        var onProgress = function (a, remaining) {
            showStatus('info', 'Applying… ' + fmtNumber(a.applied) + ' written so far' +
                (typeof remaining === 'number' ? ' • ' + fmtNumber(remaining) + ' remaining' : ''));
        };

        var work;
        if (state.batch_id) {
            work = drainBatch(state.batch_id, acc, onProgress);
        } else {
            // All batches — drain each in turn.
            work = rest.get('segments/pending-batches').then(function (data) {
                var batches = (data && data.batches) || [];
                if (!batches.length) { return acc; }
                var p = Promise.resolve(acc);
                batches.forEach(function (b) {
                    p = p.then(function () { return drainBatch(b.batch_id, acc, onProgress); });
                });
                return p;
            });
        }

        work.then(function (a) {
            btn.disabled = false;
            btn.textContent = 'Apply entire batch';
            showStatus('success', '✓ Applied ' + fmtNumber(a.applied) + ' field(s)' +
                (a.skipped > 0 ? ' (' + fmtNumber(a.skipped) + ' skipped — couldn\'t be written)' : '') + '.');
            load();
        }).catch(function (e) {
            btn.disabled = false;
            btn.textContent = 'Apply entire batch';
            showStatus('danger', e.message);
        });
    }

    function rejectEntireBatch() {
        if (!state.batch_id) { toast('Pick a batch first.', 'warning'); return; }
        if (!confirm('Reject every pending proposal in this batch?')) return;
        rest.post('segments/reject', { batch_id: state.batch_id })
            .then(function (r) {
                showStatus('success', 'Rejected ' + r.rejected + ' proposal(s).');
                load();
            })
            .catch(function (e) { showStatus('danger', e.message); });
    }

    function showStatus(kind, msg) {
        var bar = $('seocp-review-status');
        if (!bar) return;
        bar.hidden = false;
        bar.className = 'fl-message-bar fl-message-bar--' + (kind === 'success' ? 'success' : (kind === 'danger' ? 'danger' : 'info'));
        bar.textContent = msg;
        if (kind === 'success') {
            setTimeout(function () { bar.hidden = true; }, 6000);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = $('seocp-review');
        if (!root) return;
        if ((parseInt(root.getAttribute('data-pending-count'), 10) || 0) === 0) return;

        var batchSel = $('seocp-review-batch');
        if (batchSel) batchSel.addEventListener('change', function (e) {
            state.batch_id = e.target.value || '';
            state.page = 1;
            load();
        });
        var perSel = $('seocp-review-pertype');
        if (perSel) perSel.addEventListener('change', function (e) {
            state.perPage = parseInt(e.target.value, 10) || 20;
            state.page = 1;
            load();
        });

        var prev = $('seocp-review-prev');
        var next = $('seocp-review-next');
        if (prev) prev.addEventListener('click', function () { if (state.page > 1) { state.page--; load(); } });
        if (next) next.addEventListener('click', function () { state.page++; load(); });

        $('seocp-review-apply-visible').addEventListener('click', applyVisible);
        $('seocp-review-apply-batch').addEventListener('click', applyEntireBatch);
        $('seocp-review-reject-batch').addEventListener('click', rejectEntireBatch);

        load();
    });
})();
