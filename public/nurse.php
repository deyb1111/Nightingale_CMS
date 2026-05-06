<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use Nightingale\Session;
Session::requireRoleOrRedirect('nurse');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nightingale &mdash; Nurse Console</title>
  <link rel="stylesheet" href="assets/css/main.css"/>
</head>
<body>

<header class="topbar">
  <div class="topbar-brand">
    <div class="topbar-logo"><div class="topbar-logo-cross"></div></div>
    <div class="topbar-brand-text">
      <span class="topbar-name">Nightingale</span>
      <span class="topbar-tag">Nurse Console</span>
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
    <h1>Nurse Console</h1>
    <p>Daily Patient Queue &nbsp;&middot;&nbsp; Vitals &middot; Consultations &middot; Dispensing</p>
    <div class="dash-header-meta">
      <span class="badge blue">Today: <strong id="nurse-kpi-today">—</strong></span>
      <span class="badge green">This month: <strong id="nurse-kpi-month">—</strong></span>
      <span class="badge red">Emergencies: <strong id="nurse-kpi-emergencies">—</strong></span>
    </div>
  </div>

  <div class="grid g3 mb-20">
    <!-- Patient queue -->
    <div class="card" style="grid-column:span 1;">
      <div class="card-title">Today's Queue (<span id="queue-count">0</span>)</div>
      <div id="queue-list" class="queue-list"></div>
    </div>

    <!-- Vitals + Consultation -->
    <div class="card" style="grid-column:span 2;">
      <div class="card-title">
        <span id="selected-patient-name">Select a patient from the queue</span>
        &nbsp;<small id="selected-patient-meta" style="color:var(--text-3);font-weight:400;"></small>
      </div>

      <div class="inner-tabs">
        <div class="inner-tab active" data-panel="panel-vitals">Vitals</div>
        <div class="inner-tab" data-panel="panel-consult">Consultation</div>
        <div class="inner-tab" data-panel="panel-dispense">Dispense</div>
        <div class="inner-tab" data-panel="panel-referral">Referral</div>
      </div>

      <!-- VITALS -->
      <div id="panel-vitals" class="inner-panel active">
        <div class="grid g3">
          <div class="login-field"><label>BP Systolic</label><input type="number" id="vital-bps"/></div>
          <div class="login-field"><label>BP Diastolic</label><input type="number" id="vital-bpd"/></div>
          <div class="login-field"><label>Temperature (°C)</label><input type="number" step="0.1" id="vital-temp"/></div>
          <div class="login-field"><label>Pulse (bpm)</label><input type="number" id="vital-pulse"/></div>
          <div class="login-field"><label>Resp. Rate</label><input type="number" id="vital-resp"/></div>
          <div class="login-field"><label>O₂ Saturation (%)</label><input type="number" id="vital-o2"/></div>
          <div class="login-field"><label>Weight (kg)</label><input type="number" step="0.1" id="vital-weight"/></div>
        </div>
        <button class="btn-login" style="margin-top:12px;max-width:200px;" onclick="saveVitals()">Save Vitals</button>
      </div>

      <!-- CONSULTATION -->
      <div id="panel-consult" class="inner-panel">
        <div class="login-field">
          <label>Chief Complaint</label>
          <textarea id="chief-complaint" rows="2"></textarea>
        </div>
        <div class="login-field">
          <label>Case Type</label>
          <div style="display:flex;gap:14px;flex-wrap:wrap;">
            <label><input type="radio" name="case_type" value="illness" checked/> Illness</label>
            <label><input type="radio" name="case_type" value="injury"/> Injury</label>
            <label><input type="radio" name="case_type" value="follow_up"/> Follow-up</label>
            <label><input type="radio" name="case_type" value="emergency"/> Emergency</label>
          </div>
        </div>
        <div class="login-field">
          <label>Diagnosis</label>
          <input type="text" id="diagnosis"/>
        </div>
        <div class="login-field">
          <label>Nurse Notes</label>
          <textarea id="nurse-notes" rows="3"></textarea>
        </div>
        <div class="login-field">
          <label>Work Status</label>
          <select id="work-status">
            <option value="fit">Fit for duty</option>
            <option value="light_duty">Light duty</option>
            <option value="sick_leave">Sick leave</option>
            <option value="for_hospitalization">For hospitalisation</option>
          </select>
        </div>
        <div style="display:flex;gap:10px;">
          <button class="btn-login" style="max-width:200px;" onclick="openConsultation()">Open Consultation</button>
          <button class="btn-login" style="max-width:200px;background:var(--green);" onclick="closeConsultation()">Close Consultation</button>
        </div>
      </div>

      <!-- DISPENSE -->
      <div id="panel-dispense" class="inner-panel">
        <div class="grid g3">
          <div class="login-field" style="grid-column:span 2;">
            <label>Medicine</label>
            <select id="dispense-medicine-id"></select>
          </div>
          <div class="login-field">
            <label>Quantity</label>
            <input type="number" id="dispense-qty" min="1" value="1"/>
          </div>
          <div class="login-field" style="grid-column:span 3;">
            <label>Dosage Instructions</label>
            <input type="text" id="dispense-instructions" placeholder="e.g., 1 tablet every 6 hours after meals for 5 days"/>
          </div>
        </div>
        <button class="btn-login" style="max-width:240px;" onclick="dispenseMedicine()">Dispense</button>
        <hr style="margin:18px 0;border:none;border-top:1px solid var(--border-2);"/>
        <div class="card-title" style="font-size:14px;">Already dispensed</div>
        <div id="dispense-list"></div>
      </div>

      <!-- REFERRAL -->
      <div id="panel-referral" class="inner-panel">
        <div class="login-field">
          <label>Referral Type</label>
          <select id="ref-type">
            <option value="company_doctor">Company Doctor</option>
            <option value="hospital">Hospital</option>
            <option value="specialist">Specialist</option>
            <option value="emergency">Emergency</option>
          </select>
        </div>
        <div class="login-field">
          <label>Referred To</label>
          <input type="text" id="ref-to" placeholder="e.g., Pasig City General Hospital — ER"/>
        </div>
        <div class="login-field">
          <label>Reason</label>
          <textarea id="ref-reason" rows="3"></textarea>
        </div>
        <button class="btn-login" style="max-width:240px;" onclick="createReferral()">Issue Referral</button>
      </div>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script src="assets/js/config.js"></script>
<script src="assets/js/api.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/auth.js"></script>
<script src="assets/js/nurse.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', () => initDashboard('nurse'));
</script>
</body>
</html>
