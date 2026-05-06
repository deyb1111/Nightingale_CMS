<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en"><head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nightingale — Reset Password</title>
  <link rel="stylesheet" href="assets/css/main.css"/>
</head><body class="login-page">

<div class="login-screen">
  <div class="login-card" style="max-width:420px;">
    <div class="login-brand">
      <div class="login-logo"><div class="login-logo-cross"></div></div>
      <h1>Choose a New Password</h1>
    </div>

    <div class="login-field">
      <label>New password (10+ characters)</label>
      <input type="password" id="np" autocomplete="new-password"/>
    </div>
    <div class="login-field">
      <label>Confirm new password</label>
      <input type="password" id="np2" autocomplete="new-password"/>
    </div>

    <div class="login-error" id="rp-error"></div>
    <button class="btn-login" onclick="submitNew()">Reset Password</button>
  </div>
</div>

<script src="assets/js/config.js"></script>
<script src="assets/js/api.js"></script>
<script src="assets/js/app.js"></script>
<script>
const params = new URLSearchParams(location.search);
async function submitNew() {
  hideError('rp-error');
  const a = document.getElementById('np').value;
  const b = document.getElementById('np2').value;
  if (a.length < 10) return showError('rp-error', 'Password must be at least 10 characters.');
  if (a !== b)        return showError('rp-error', 'Passwords do not match.');
  try {
    await API.post('auth/password-reset.php', {
      portal:       params.get('portal') || 'user',
      token:        params.get('token'),
      new_password: a,
    });
    location.replace((params.get('portal') === 'admin') ? 'admin-login.php' : 'login.php');
  } catch (err) {
    showError('rp-error', 'This reset link has expired or is invalid.');
  }
}
</script></body></html>
