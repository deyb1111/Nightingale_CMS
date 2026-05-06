/* ============================================================
   NIGHTINGALE — Authentication (server-driven)
   --------------------------------------------------------------
   No client-side TOTP math, no hard-coded users.  Each function
   simply calls the corresponding /api/auth/*.php endpoint and
   reacts to the response.
============================================================ */
(function () {
  const cfg = window.APP_CONFIG;
  let pendingTotpToken  = '';
  let totpExpectedRole  = '';
  let isAdminPortal     = false;

  function $(id) { return document.getElementById(id); }
  function val(id) { const el = $(id); return el ? el.value.trim() : ''; }

  // ── Login form ──────────────────────────────────────────────
  async function doLogin(opts) {
    opts = opts || {};
    isAdminPortal = !!opts.admin;

    hideError('login-error');

    const username = val('login-user');
    const password = val('login-pass');

    if (!username || !password) {
      showError('login-error', 'Please enter your username and password.');
      shake($('card-login'));
      return;
    }

    const ep = isAdminPortal ? cfg.endpoints.adminLogin : cfg.endpoints.login;
    let resp;
    try {
      resp = await API.post(ep, { username, password });
    } catch (err) {
      const msg = mapAuthError(err);
      showError('login-error', msg);
      shake($('card-login'));
      return;
    }

    if (resp.stage === 'totp_required') {
      pendingTotpToken = resp.pending_token;
      totpExpectedRole = resp.role || (isAdminPortal ? 'admin' : 'nurse');
      openTOTP(resp);
      return;
    }
    if (resp.stage === 'totp_setup_required') {
      pendingTotpToken = resp.pending_token;
      totpExpectedRole = resp.role || (isAdminPortal ? 'admin' : 'nurse');
      // Hand off to the dedicated enrollment page
      const url  = 'totp-setup.php?token=' + encodeURIComponent(pendingTotpToken)
                 + '&portal=' + (isAdminPortal ? 'admin' : 'user');
      location.replace(url);
      return;
    }
    if (resp.stage === 'authenticated') {
      finishLogin(resp.role);
    }
  }

  function mapAuthError(err) {
    const e = (err && err.body && err.body.error) || (err && err.message) || '';
    if (e === 'invalid_credentials')   return 'Username or password is incorrect.';
    if (e === 'too_many_attempts')     return 'Too many failed attempts. Try again in 15 minutes.';
    if (e === 'ip_not_allowed')        return 'This portal is not accessible from your network.';
    if (e === 'invalid_totp')          return 'Code is incorrect. Try again.';
    if (e === 'pending_token_expired') return 'Login session expired. Please sign in again.';
    return 'Sign-in failed. Please try again.';
  }

  // ── TOTP overlay ────────────────────────────────────────────
  function openTOTP(resp) {
    const totpScreen = $('totp-screen');
    if (!totpScreen) return;
    const labelEl = $('totp-label');
    if (labelEl && resp.label) labelEl.textContent = resp.label;
    totpScreen.classList.add('visible');
    const first = document.querySelector('.totp-digit');
    if (first) first.focus();
    startTotpCountdown();
  }

  function closeTOTP() {
    const totpScreen = $('totp-screen');
    if (totpScreen) totpScreen.classList.remove('visible');
    document.querySelectorAll('.totp-digit').forEach(i => (i.value = ''));
  }

  let _countdownTimer = null;
  function startTotpCountdown() {
    const tEl = $('totp-timer');
    if (!tEl) return;
    if (_countdownTimer) clearInterval(_countdownTimer);
    function tick() {
      const sec = 30 - (Math.floor(Date.now() / 1000) % 30);
      tEl.textContent = sec + 's';
    }
    tick();
    _countdownTimer = setInterval(tick, 1000);
  }

  async function verifyTOTP() {
    hideError('totp-error');
    const digits = Array.from(document.querySelectorAll('.totp-digit'))
                        .map(i => i.value).join('').trim();
    let code = digits;
    const backup = $('totp-backup-code');
    if (backup && backup.value.trim()) code = backup.value.trim().toUpperCase();

    if (!code || (code.length < 6)) {
      showError('totp-error', 'Enter all 6 digits or a backup code.');
      shake($('totp-card'));
      return;
    }

    const ep = isAdminPortal ? cfg.endpoints.adminVerifyTotp : cfg.endpoints.verifyTotp;
    let resp;
    try {
      resp = await API.post(ep, { pending_token: pendingTotpToken, code });
    } catch (err) {
      showError('totp-error', mapAuthError(err));
      shake($('totp-card'));
      return;
    }
    if (resp.stage === 'authenticated') {
      finishLogin(resp.role);
    }
  }

  function finishLogin(role) {
    sessionStorage.setItem(cfg.sessionStorageKey, JSON.stringify({
      role,
      since: Date.now(),
    }));
    const target = cfg.roleRoutes[role] || 'login.php';
    location.replace(target);
  }

  // ── Logout ──────────────────────────────────────────────────
  async function doLogout() {
    try { await API.post(cfg.endpoints.logout); }
    catch { /* ignore — we're logging out anyway */ }
    sessionStorage.removeItem(cfg.sessionStorageKey);
    const target = location.pathname.includes('admin') ? 'admin-login.php' : 'login.php';
    location.replace(target);
  }

  // ── Session guard for dashboards ────────────────────────────
  async function requireAuth(expectedRole) {
    let session;
    try {
      session = await API.get(cfg.endpoints.session, {}, { skipAuthRedirect: true });
    } catch { session = { authenticated: false }; }

    if (!session.authenticated) {
      const target = expectedRole === 'admin' ? 'admin-login.php' : 'login.php';
      location.replace(target);
      return null;
    }
    if (session.user && session.user.role !== expectedRole) {
      const target = cfg.roleRoutes[session.user.role] || 'login.php';
      location.replace(target);
      return null;
    }
    if (session.csrf_token) API.setCsrf(session.csrf_token);
    return session.user;
  }

  // ── Wire up TOTP digit auto-advance on any page that has it ─
  document.addEventListener('DOMContentLoaded', () => {
    const digits = Array.from(document.querySelectorAll('.totp-digit'));
    digits.forEach((d, i) => {
      d.addEventListener('input', e => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 1);
        if (e.target.value && i < digits.length - 1) digits[i + 1].focus();
      });
      d.addEventListener('keydown', e => {
        if (e.key === 'Backspace' && !e.target.value && i > 0) digits[i - 1].focus();
        if (e.key === 'Enter') verifyTOTP();
      });
    });
  });

  // expose
  window.doLogin    = doLogin;
  window.doLogout   = doLogout;
  window.openTOTP   = openTOTP;
  window.closeTOTP  = closeTOTP;
  window.verifyTOTP = verifyTOTP;
  window.requireAuth = requireAuth;
})();
