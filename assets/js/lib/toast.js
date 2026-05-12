(function () {
    'use strict';
    var SeoCp = window.SeoCp = window.SeoCp || {};

    function host() {
        var h = document.querySelector('.fl-toast-host');
        if (!h) {
            h = document.createElement('div');
            h.className = 'fl-toast-host';
            document.body.appendChild(h);
        }
        return h;
    }

    SeoCp.toast = function (message, kind) {
        var el = document.createElement('div');
        el.className = 'fl-toast fl-toast--' + (kind || 'info');
        el.setAttribute('role', 'status');
        el.textContent = message;
        host().appendChild(el);
        setTimeout(function () { el.remove(); }, 4500);
    };
})();
