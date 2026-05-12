(function () {
    'use strict';
    var SeoCp = window.SeoCp = window.SeoCp || {};

    SeoCp.escape = function (s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    SeoCp.h = function (tag, attrs, children) {
        var el = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'class') el.className = attrs[k];
                else if (k.indexOf('on') === 0) el.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
                else if (k === 'html') el.innerHTML = attrs[k];
                else el.setAttribute(k, attrs[k]);
            });
        }
        (children || []).forEach(function (c) {
            if (c == null) return;
            el.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        });
        return el;
    };
})();
