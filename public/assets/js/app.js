/* ============================================================
   NIGHTINGALE — Dashboard Interactions & Utilities
   ============================================================ */

/**
 * initDashboard — call on each dashboard page load.
 * Validates session via /api/auth/session.php, populates topbar.
 * @param {string} expectedRole  'nurse' | 'patient' | 'admin'
 */
async function initDashboard(expectedRole) {
  const session = await requireAuth(expectedRole);
  if (!session) return null;

  const nameEl     = document.getElementById('topbar-name');
  const roleEl     = document.getElementById('topbar-role');
  const initialsEl = document.getElementById('topbar-initials');
  if (nameEl)     nameEl.textContent     = session.label || session.username || '';
  if (roleEl)     roleEl.textContent     =
    (session.role || '').charAt(0).toUpperCase() + (session.role || '').slice(1);
  if (initialsEl) initialsEl.textContent = session.initials || '--';

  updateDateTime();
  setInterval(updateDateTime, 30000);

  // Hand the session down to dashboard-specific code.
  document.dispatchEvent(new CustomEvent('nightingale:session', { detail: session }));
  return session;
}

function updateDateTime() {
  const el = document.getElementById('topbar-datetime');
  if (!el) return;
  const now = new Date();
  el.textContent = now.toLocaleDateString('en-US', {
    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
  }) + '  ·  ' + now.toLocaleTimeString('en-US', {
    hour: '2-digit', minute: '2-digit'
  });
}

/* ── INNER TAB SWITCHING ─────────────────────────────────── */

function switchInner(tab, panelId) {
  const card  = tab.closest('.card');
  const scope = card || document;
  scope.querySelectorAll('.inner-tab').forEach(t  => t.classList.remove('active'));
  scope.querySelectorAll('.inner-panel').forEach(p => p.classList.remove('active'));
  tab.classList.add('active');
  const panel = document.getElementById(panelId);
  if (panel) panel.classList.add('active');
}

function selectPatient(el) {
  document.querySelectorAll('.queue-item').forEach(i => i.classList.remove('active-patient'));
  el.classList.add('active-patient');
}

/* ── TOAST + ERROR ───────────────────────────────────────── */

function showToast(msg) {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.classList.add('visible');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('visible'), 3200);
}

function showError(id, msg) {
  const el = document.getElementById(id);
  if (!el) return;
  el.textContent = msg;
  el.classList.add('show');
}
function hideError(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('show');
}

function shake(el) {
  if (!el) return;
  el.style.animation = 'none';
  el.offsetHeight;
  el.style.animation = 'shake 0.35s ease';
  setTimeout(() => (el.style.animation = ''), 400);
}

/* ── Helpers ─────────────────────────────────────────────── */

function fmtDate(d) {
  if (!d) return '—';
  const dt = new Date(d);
  if (isNaN(dt.getTime())) return d;
  return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function fmtTime(t) {
  if (!t) return '—';
  // t may be 'HH:MM:SS'
  const m = String(t).match(/^(\d{2}):(\d{2})/);
  if (!m) return t;
  let h = parseInt(m[1], 10);
  const mm = m[2];
  const ampm = h >= 12 ? 'PM' : 'AM';
  h = ((h + 11) % 12) + 1;
  return `${h}:${mm} ${ampm}`;
}

function escapeHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function caseTypeBadge(t) {
  const cls = { illness: 'blue', injury: 'amber', follow_up: 'green', emergency: 'red' };
  const lbl = { illness: 'Illness', injury: 'Injury', follow_up: 'Follow-up', emergency: 'Emergency' };
  return `<span class="badge ${cls[t] || 'blue'}">${lbl[t] || t}</span>`;
}

function statusBadge(s) {
  const cls = { waiting: 'amber', in_progress: 'blue', done: 'green', cancelled: 'red' };
  return `<span class="badge ${cls[s] || 'blue'}">${(s || '').replace('_', ' ')}</span>`;
}

/* ── AUTO-WIRE INNER TABS via data-panel ─────────────────── */

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.inner-tab[data-panel]').forEach(tab => {
    tab.addEventListener('click', function () {
      switchInner(this, this.getAttribute('data-panel'));
    });
  });
});
