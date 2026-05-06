<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use Nightingale\Session;

Session::start();
// Already logged in?  Bounce to the matching dashboard.
if (Session::isAuth()) {
    $role  = $_SESSION['role'] ?? '';
    $route = ['nurse' => 'nurse.php', 'patient' => 'patient.php', 'admin' => 'admin.php'];
    if (isset($route[$role])) {
        header('Location: ' . $route[$role]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nightingale — Login</title>
  <link rel="stylesheet" href="assets/css/main.css"/>
</head>
<body class="login-page">

<div id="login-screen" class="login-screen">
  <div class="login-card" id="card-login">
    <div class="login-brand">
      <div class="login-logo"><div class="login-logo-cross"></div></div>
      <h1>Nightingale</h1>
      <p>Clinical Management System &nbsp;|&nbsp; v2.0</p>
    </div>

    <div class="login-field">
      <label for="login-user">Username</label>
      <input type="text" id="login-user" placeholder="Enter your username" autocomplete="username"/>
    </div>

    <div class="login-field">
      <label for="login-pass">Password</label>
      <input type="password" id="login-pass" placeholder="Enter your password" autocomplete="current-password"/>
    </div>

    <div class="login-error" id="login-error"></div>

    <button class="btn-login" onclick="doLogin()">Continue</button>

    <div style="text-align:center;margin-top:18px;">
      <a href="admin-login.php" style="font-size:11px;color:var(--text-3,#6b7280);text-decoration:none;opacity:0.5;" tabindex="-1">
        Administration Portal
      </a>
    </div>

    <div style="text-align:center;margin-top:8px;">
      <a href="password-request.php" style="font-size:12px;color:var(--text-3,#6b7280);">
        Forgot password?
      </a>
    </div>
  </div>
</div>

<!-- TOTP overlay -->
<div id="totp-screen" class="totp-overlay">
  <div class="totp-card" id="totp-card">
    <div class="totp-badge">
      <span class="totp-badge-dot"></span>
      Two-Factor Authentication
    </div>

    <h2>Verify Your Identity</h2>
    <p>
      Open your authenticator app and enter the 6-digit code
      for <strong id="totp-label"></strong>.
    </p>

    <div class="totp-digits" id="totp-digits">
      <?php for ($i = 0; $i < 6; $i++): ?>
        <input class="totp-digit" maxlength="1" inputmode="numeric" pattern="[0-9]"/>
      <?php endfor; ?>
    </div>

    <div class="totp-timer">
      Code refreshes in <span id="totp-timer">30s</span>
    </div>

    <details style="margin-top:14px;font-size:13px;color:var(--text-2,#444);">
      <summary>Use a backup code instead</summary>
      <input type="text" id="totp-backup-code" placeholder="XXXXX-XXXXX"
             style="margin-top:8px;width:100%;padding:8px;border-radius:6px;border:1px solid var(--border-2,#ddd);"/>
    </details>

    <div class="totp-error" id="totp-error"></div>

    <button class="btn-login" onclick="verifyTOTP()">Verify</button>
    <button class="btn-totp-back" onclick="closeTOTP()">Return to Login</button>
  </div>
</div>

<script src="assets/js/config.js"></script>
<script src="assets/js/api.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/auth.js"></script>
</body>
</html>
