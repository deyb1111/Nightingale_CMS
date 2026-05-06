<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nightingale — Set up 2FA</title>
  <link rel="stylesheet" href="assets/css/main.css"/>
</head>
<body class="login-page">

<div class="login-screen">
  <div class="login-card" style="max-width:480px;">
    <div class="login-brand">
      <div class="login-logo"><div class="login-logo-cross"></div></div>
      <h1>Set Up 2FA</h1>
      <p>Two-factor authentication is required for this account</p>
    </div>

    <ol style="padding-left:20px;font-size:13px;color:var(--text-2,#444);">
      <li>Install <strong>Google Authenticator</strong>, <strong>Authy</strong>, or <strong>1Password</strong> on your phone.</li>
      <li>Scan the QR code below.</li>
      <li>Enter the 6-digit code from your app to confirm.</li>
    </ol>

    <div id="qr-area" style="display:flex;justify-content:center;margin:16px 0;"></div>

    <div style="font-size:12px;color:var(--text-3,#6b7280);text-align:center;">
      Manual entry secret: <code id="manual-secret" style="background:#f4f4f4;padding:4px 8px;border-radius:4px;"></code>
    </div>

    <div class="login-field" style="margin-top:24px;">
      <label for="setup-code">6-digit code from your app</label>
      <input type="text" id="setup-code" maxlength="6" inputmode="numeric"
             pattern="\d{6}" autocomplete="one-time-code"
             style="font-size:18px;letter-spacing:8px;text-align:center;"/>
    </div>

    <div class="login-error" id="setup-error"></div>

    <button class="btn-login" id="setup-confirm-btn">Verify & Enable</button>

    <div id="codes-area" class="hidden" style="margin-top:24px;">
      <h3 style="font-size:14px;">Backup recovery codes</h3>
      <p style="font-size:12px;color:var(--text-3,#6b7280);">
        Store these codes safely.  Each one can be used <strong>once</strong>
        if you lose access to your authenticator app.
      </p>
      <pre id="codes-list" style="background:#f4f4f4;padding:12px;border-radius:6px;font-family:monospace;"></pre>
      <button class="btn-login" id="continue-login-btn">Continue to Sign In</button>
    </div>
  </div>
</div>

<script src="assets/js/config.js"></script>
<script src="assets/js/api.js"></script>
<script src="assets/js/app.js"></script>
<script>
(async function () {
  const params = new URLSearchParams(location.search);
  let pendingToken = params.get('token') || '';
  if (!pendingToken) { location.replace('login.php'); return; }

  let init;
  try {
    init = await API.post('auth/totp-setup.php', { action: 'init', pending_token: pendingToken });
  } catch (e) {
    showError('setup-error', 'This setup link has expired. Please log in again.');
    return;
  }
  pendingToken = init.pending_token;
  document.getElementById('qr-area').innerHTML = init.qr_svg;
  document.getElementById('manual-secret').textContent = init.secret.match(/.{1,4}/g).join(' ');

  document.getElementById('setup-confirm-btn').addEventListener('click', async () => {
    hideError('setup-error');
    const code = document.getElementById('setup-code').value.trim();
    if (!/^\d{6}$/.test(code)) { showError('setup-error', 'Enter a 6-digit code.'); return; }
    try {
      const resp = await API.post('auth/totp-setup.php',
        { action: 'confirm', pending_token: pendingToken, code });
      const list = document.getElementById('codes-list');
      list.textContent = resp.backup_codes.join('\n');
      document.getElementById('codes-area').classList.remove('hidden');
      document.getElementById('setup-confirm-btn').classList.add('hidden');
    } catch (err) {
      showError('setup-error', err.body && err.body.error === 'invalid_totp'
        ? 'Code is incorrect. Try again.'
        : 'Setup failed: ' + (err.body && err.body.error || err.message));
    }
  });

  document.getElementById('continue-login-btn').addEventListener('click', () => {
    location.replace(params.get('portal') === 'admin' ? 'admin-login.php' : 'login.php');
  });
})();
</script>
</body>
</html>
