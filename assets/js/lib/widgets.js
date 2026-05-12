(function () {
    'use strict';
    var SeoCp = window.SeoCp = window.SeoCp || {};
    var esc = SeoCp.escape;

    /**
     * Mounts a SERP preview into a container.
     * Returns { update({title, description, url}) }.
     */
    SeoCp.serp = function (host, initial) {
        host.innerHTML =
            '<div class="fl-serp">' +
                '<div class="fl-serp__url"></div>' +
                '<div class="fl-serp__title"></div>' +
                '<div class="fl-serp__desc"></div>' +
            '</div>';
        var $url = host.querySelector('.fl-serp__url');
        var $title = host.querySelector('.fl-serp__title');
        var $desc = host.querySelector('.fl-serp__desc');
        function set(s) {
            if (!s) return;
            if ('url' in s)   $url.textContent = s.url || '';
            if ('title' in s) $title.textContent = s.title || '';
            if ('description' in s) $desc.textContent = s.description || '';
        }
        set(initial || {});
        return { update: set };
    };

    /**
     * Mounts a Fluent character counter that scores against [min, max].
     * Returns { update(value) }.
     */
    SeoCp.counter = function (host, min, max) {
        host.classList.add('fl-counter');
        host.innerHTML = '<span class="fl-counter__bar"><span class="fl-counter__fill" style="width:0%"></span></span><span class="fl-counter__text"></span>';
        var $fill = host.querySelector('.fl-counter__fill');
        var $text = host.querySelector('.fl-counter__text');
        function update(value) {
            var n = (value || '').length;
            var pct = max ? Math.min(100, Math.round((n / max) * 100)) : 0;
            $fill.style.width = pct + '%';
            var state = 'warn';
            if (n === 0) state = 'warn';
            else if (n < min) state = 'warn';
            else if (n > max) state = 'over';
            else state = 'ok';
            host.setAttribute('data-state', state);
            $text.textContent = n + ' / ' + max + ' chars';
        }
        update('');
        return { update: update };
    };

    /**
     * Wires up clickable, keyboard-accessible step jumps in a stepper.
     * onJump(stepIndex) is invoked when a "done" step is clicked.
     */
    SeoCp.stepperJumps = function (root, onJump) {
        root.querySelectorAll('.js-step-jump').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.getAttribute('data-state') !== 'done') return;
                onJump(parseInt(btn.getAttribute('data-step'), 10));
            });
        });
    };

    /**
     * Update stepper visual state by active index.
     */
    SeoCp.setStep = function (root, activeIndex) {
        var steps = root.querySelectorAll('.fl-stepper__step');
        steps.forEach(function (step, i) {
            var state = i < activeIndex ? 'done' : (i === activeIndex ? 'active' : 'pending');
            step.setAttribute('data-state', state);
            if (state === 'pending') step.setAttribute('aria-disabled', 'true');
            else step.removeAttribute('aria-disabled');
            step.disabled = state === 'pending';
        });
    };

    /** Toggle wizard panels by data-step attr. */
    SeoCp.showPanel = function (host, idx) {
        host.querySelectorAll('.fl-wizard__panel').forEach(function (p) {
            p.setAttribute('data-active', String(parseInt(p.getAttribute('data-step'), 10) === idx));
        });
    };
})();
