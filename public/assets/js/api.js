/* ============================================================
   NIGHTINGALE — Fetch wrapper
   --------------------------------------------------------------
   • Adds X-CSRF-Token to every state-changing request.
   • Redirects to login on 401.
   • Throws an Error containing the parsed JSON body on non-2xx.
============================================================ */
window.API = (function () {
  const cfg = window.APP_CONFIG;

  function url(path, params) {
    let u = cfg.apiBase.replace(/\/+$/, '') + '/' + path.replace(/^\/+/, '');
    if (params && Object.keys(params).length) {
      const qs = new URLSearchParams(params).toString();
      u += (u.includes('?') ? '&' : '?') + qs;
    }
    return u;
  }

  let _csrf = '';
  function setCsrf(t) { _csrf = t || ''; }
  function getCsrf()  { return _csrf; }

  async function rawFetch(method, path, opts = {}) {
    const headers = Object.assign(
      { 'Accept': 'application/json' },
      opts.headers || {}
    );

    let body;
    if (opts.body !== undefined) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(opts.body);
    }
    if (method !== 'GET' && _csrf) headers['X-CSRF-Token'] = _csrf;

    const resp = await fetch(url(path, opts.params), {
      method,
      credentials: 'same-origin',
      cache:       'no-store',
      headers,
      body,
    });

    let data = {};
    try   { data = await resp.json(); }
    catch { /* non-JSON response — keep empty */ }

    if (resp.status === 401 && !opts.skipAuthRedirect) {
      // Force back to the login portal that matches the current page.
      const target = location.pathname.includes('admin') ? 'admin-login.php' : 'login.php';
      if (!location.pathname.endsWith(target)) location.replace(target);
    }
    if (!resp.ok) {
      const err = new Error(data.error || ('http_' + resp.status));
      err.status = resp.status;
      err.body = data;
      throw err;
    }
    return data;
  }

  return {
    setCsrf,
    getCsrf,
    get:    (path, params, opts)  => rawFetch('GET',    path, Object.assign({}, opts, { params })),
    post:   (path, body,   opts)  => rawFetch('POST',   path, Object.assign({}, opts, { body })),
    patch:  (path, body,   opts)  => rawFetch('PATCH',  path, Object.assign({}, opts, { body })),
    del:    (path,         opts)  => rawFetch('DELETE', path, opts),
  };
})();
