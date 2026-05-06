<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en"><head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nightingale — Forgot Password</title>
  <link rel="stylesheet" href="assets/css/main.css"/>
</head><body class="login-page">

<div class="login-screen">
  <div class="login-card" style="max-width:420px;">
    <div class="login-brand">
      <div class="login-logo"><div class="login-logo-cross"></div></div>
      <h1>Reset Password</h1>
      <p>We'll email you a one-time reset link.</p>
    </div>

    <div class="login-field">
      <label for="rp-email">Email address</label>
      <input type="email" id="rp-email" autocomplete="email"/>
    </div>

    <div class="login-error" id="rp-error"></div>
    <div id="rp-success" class="hidden" style="background:#e8f5e9;color:#2e7d32;padding:12px;border-radius:6px;text-align:center;">
      If an account exists, a reset link has been sent.
    </div>

    <button class="btn-login" onclick="submitReset()">Send Reset Link</button>
    <div style="text-align:center;margin-top:18px;"><a href="login.php" style="font-size:12px;">← Back to login</a></div>
  </div>
</div>

<script src="assets/js/config.js"></script>
<script src="assets/js/api.js"></script>
<script src="assets/js/app.js"></script>
<script>
async function submitReset() {
  hideError('rp-error');
  const email = document.getElementById('rp-email').value.trim();
  if (!email) return showError('rp-error', 'Enter your email address.');
  try {
    await API.post('auth/password-request.php', { email, portal: 'user' });
    document.getElementById('rp-success').classList.remove('hidden');
  } catch (err) {
    showError('rp-error', 'Could not process request. Please try again.');
  }
}
</script></body></html>
