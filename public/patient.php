<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use Nightingale\Session;
Session::requireRoleOrRedirect('patient');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nightingale &mdash; My Health</title>
  <link rel="stylesheet" href="assets/css/main.css"/>
</head>
<body>

<header class="topbar">
  <div class="topbar-brand">
    <div class="topbar-logo"><div class="topbar-logo-cross"></div></div>
    <div class="topbar-brand-text">
      <span class="topbar-name">Nightingale</span>
      <span class="topbar-tag">My Health</span>
    </div>
  </div>
  <div class="topbar-center">
    <span class="status-dot"></span>&nbsp;<span id="topbar-datetime"></span>
  </div>
  <div class="topbar-right">
    <div class="topbar-user">
      <div class="topbar-avatar" id="topbar-initials">--</div>
      <div class="topbar-user-info">
        <span class="topbar-user-name" id="topbar-name"></span>
        <span class="topbar-user-role" id="topbar-role"></span>
      </div>
    </div>
    <button class="btn-logout" onclick="doLogout()">Sign Out</button>
  </div>
</header>

<div class="dashboard">

  <div class="dash-header">
    <h1>My Health</h1>
    <p>Personal medical record · Visit history · APE summary</p>
  </div>

  <!-- Today queue card -->
  <div id="p-queue-card" class="card hidden mb-20">
    <div class="card-title">You are in today's queue</div>
    <div style="font-size:30px;font-weight:600;">#<span id="p-queue-number"></span></div>
    <div style="color:var(--text-3);">Status: <strong id="p-queue-status"></strong></div>
  </div>

  <!-- Profile -->
  <div class="grid g2 mb-20">
    <div class="card">
      <div class="card-title">Profile</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
        <div><strong>Full Name:</strong> <span id="p-fullname"></span></div>
        <div><strong>Employee #:</strong> <span id="p-employee-no"></span></div>
        <div><strong>Department:</strong> <span id="p-dept"></span></div>
        <div><strong>Blood Type:</strong> <span id="p-blood"></span></div>
        <div style="grid-column:1/-1;"><strong>Allergies:</strong> <span id="p-allergies"></span></div>
        <div style="grid-column:1/-1;"><strong>Emergency Contact:</strong> <span id="p-emergency"></span></div>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Snapshot</div>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px;">
        <div class="stat-card blue"><div class="stat-label">Total Visits</div><div class="stat-value" id="p-stat-visits">—</div></div>
        <div class="stat-card amber"><div class="stat-label">Emergencies</div><div class="stat-value" id="p-stat-emergencies">—</div></div>
        <div class="stat-card red"><div class="stat-label">Referrals</div><div class="stat-value" id="p-stat-referrals">—</div></div>
        <div class="stat-card green"><div class="stat-label">Last Visit</div><div class="stat-value" style="font-size:18px;" id="p-stat-last">—</div></div>
      </div>
      <hr style="margin:16px 0;border:none;border-top:1px solid var(--border-2);"/>
      <div style="font-size:13px;color:var(--text-3);">Last recorded vitals</div>
      <div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:6px;">
        <div><strong>BP:</strong> <span id="p-last-bp">—</span></div>
        <div><strong>Temp:</strong> <span id="p-last-temp">—</span></div>
        <div><strong>Weight:</strong> <span id="p-last-weight">—</span></div>
      </div>
    </div>
  </div>

  <!-- Visit history -->
  <div class="card mb-20">
    <div class="card-title">Visit History</div>
    <table id="history-table">
      <thead><tr><th>Date</th><th>Case</th><th>Diagnosis</th><th>Work Status</th><th>BP</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>

  <!-- APE history -->
  <div class="card mb-20">
    <div class="card-title">Annual Physical Examinations</div>
    <table id="patient-ape-table">
      <thead><tr><th>Year</th><th>Date</th><th>BP</th><th>Weight</th><th>BMI</th><th>Status</th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div id="toast" class="toast"></div>

<script src="assets/js/config.js"></script>
<script src="assets/js/api.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/auth.js"></script>
<script src="assets/js/patient.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => initDashboard('patient'));
</script>
</body>
</html>
