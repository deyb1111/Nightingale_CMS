<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use Nightingale\Session;
Session::requireRoleOrRedirect('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nightingale &mdash; Admin Overview</title>
  <link rel="stylesheet" href="assets/css/main.css"/>
</head>
<body>

<header class="topbar">
  <div class="topbar-brand">
    <div class="topbar-logo"><div class="topbar-logo-cross"></div></div>
    <div class="topbar-brand-text">
      <span class="topbar-name">Nightingale</span>
      <span class="topbar-tag">Clinical Management System</span>
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
    <h1>Admin Overview</h1>
    <p>Clinic Performance Monitor</p>
    <div class="dash-header-meta">
      <span class="badge blue">System Active</span>
    </div>
  </div>

  <!-- KPI Row -->
  <div class="grid g4 mb-20">
    <div class="stat-card blue">
      <div class="stat-label">Consultations Today</div>
      <div class="stat-value" id="kpi-today-consults">—</div>
      <div class="stat-sub">Live count</div>
    </div>
    <div class="stat-card amber">
      <div class="stat-label">Emergencies Today</div>
      <div class="stat-value" id="kpi-today-referrals">—</div>
      <div class="stat-sub">Same-day urgent cases</div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Consultations &mdash; This Month</div>
      <div class="stat-value" id="kpi-month-consults">—</div>
      <div class="stat-sub">Calendar month total</div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">APE Completed (this year)</div>
      <div class="stat-value" id="kpi-ape-completed">—</div>
      <div class="stat-sub" id="kpi-ape-sub"></div>
      <div class="progress-bar" id="kpi-ape-bar" style="margin-top:10px;">
        <div class="progress-fill"></div>
      </div>
      <div style="font-size:11px;color:var(--text-3);text-align:right" id="kpi-ape-percent"></div>
    </div>
  </div>

  <!-- Top Illnesses + Consultations by Department -->
  <div class="grid g2 mb-20">
    <div class="card">
      <div class="card-title">Top Illnesses / Complaints &mdash; This Month</div>
      <div class="donut-legend" id="illness-legend"></div>
    </div>

    <div class="card">
      <div class="card-title">Consultations by Department</div>
      <table id="dept-table">
        <thead>
          <tr><th>Department</th><th>Visits</th><th>Referrals</th><th>Trend</th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <!-- Inventory + Referrals -->
  <div class="grid g2 mb-20">
    <div class="card">
      <div class="card-title">Medicine Inventory &mdash; Critical / Low Stock</div>
      <div id="inventory-list"></div>
    </div>

    <div class="card">
      <div class="card-title">Recent Referrals (this month)</div>
      <div id="referrals-list"></div>
    </div>
  </div>

  <!-- APE Compliance -->
  <div class="grid g1 mb-20">
    <div class="card">
      <div class="card-title">Annual Physical Examination &mdash; Compliance</div>
      <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
        <div style="flex:1;min-width:240px;">
          <strong style="font-size:32px;" id="ape-overall-percent">—</strong>
          <div style="color:var(--text-3);" id="ape-overall-sub"></div>
          <div class="progress-bar" id="ape-overall-bar"><div class="progress-fill"></div></div>
        </div>
        <div style="flex:2;min-width:380px;">
          <table id="ape-table">
            <thead><tr><th>Department</th><th>Done</th><th>Pending</th><th>Rate</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Audit log -->
  <div class="grid g1 mb-20">
    <div class="card">
      <div class="card-title">System Audit Log &mdash; Today</div>
      <table id="audit-table">
        <thead>
          <tr><th>Time</th><th>Type</th><th>Record</th><th>Summary</th><th>Actor</th><th>Action</th></tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>

  <!-- Clinic settings -->
  <div class="grid g1 mb-20">
    <div class="card">
      <div class="card-title">Clinic Settings</div>
      <div class="grid g2">
        <div class="login-field">
          <label>Clinic Name</label><input type="text" id="clinic_name"/>
        </div>
        <div class="login-field">
          <label>Contact Email</label><input type="email" id="contact_email"/>
        </div>
        <div class="login-field" style="grid-column:1/-1;">
          <label>Address</label><input type="text" id="clinic_address"/>
        </div>
        <div class="login-field">
          <label>Opening Time</label><input type="time" id="opening_time"/>
        </div>
        <div class="login-field">
          <label>Closing Time</label><input type="time" id="closing_time"/>
        </div>
      </div>
      <button class="btn-login" style="margin-top:14px;max-width:240px;" onclick="adminSaveSettings()">Save Settings</button>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script src="assets/js/config.js"></script>
<script src="assets/js/api.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/auth.js"></script>
<script src="assets/js/admin.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => initDashboard('admin'));
</script>
</body>
</html>
