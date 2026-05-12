(function () {
    'use strict';
    var SeoCp = window.SeoCp = window.SeoCp || {};

    function nonceHeaders() {
        var h = { 'Content-Type': 'application/json' };
        if (window.seocpData && window.seocpData.nonce) {
            h['X-WP-Nonce'] = window.seocpData.nonce;
        }
        return h;
    }

    function url(path) {
        var base = (window.seocpData && window.seocpData.restBase) || '/wp-json/seocp/v1';
        return base.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
    }

    function request(method, path, body) {
        var opts = { method: method, headers: nonceHeaders(), credentials: 'same-origin' };
        if (body !== undefined) opts.body = JSON.stringify(body);
        return fetch(url(path), opts).then(function (r) {
            return r.text().then(function (txt) {
                var data = txt ? JSON.parse(txt) : null;
                if (!r.ok) {
                    var msg = (data && (data.error || data.message)) || ('HTTP ' + r.status);
                    var err = new Error(msg);
                    err.status = r.status;
                    err.data = data;
                    throw err;
                }
                return data;
            });
        });
    }

    SeoCp.rest = {
        get:  function (p) { return request('GET', p); },
        post: function (p, b) { return request('POST', p, b); },
        put:  function (p, b) { return request('PUT', p, b); },
        del:  function (p) { return request('DELETE', p); }
    };
})();
