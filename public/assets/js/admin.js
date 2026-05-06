/* ============================================================
   NIGHTINGALE — Admin Dashboard
   Hydrates the static markup with /api/reports.php, /api/audit.php,
   /api/medicines.php, /api/ape.php, /api/settings.php.
============================================================ */
(function () {
  const cfg = window.APP_CONFIG;

  document.addEventListener('nightingale:session', () => {
    Promise.all([
      loadKpis(),
      loadIllness(),
      loadByDepartment(),
      loadInventory(),
      loadReferrals(),
      loadApe(),
      loadAudit(),
      loadSettings(),
    ]).catch(err => console.error('[admin] load error', err));
  });

  // ── KPI cards ───────────────────────────────────────────────
  async function loadKpis() {
    const r = await API.get(cfg.endpoints.reports, { report: 'kpis' });
    setKpiValue('kpi-today-consults',   r.today.consultations);
    setKpiValue('kpi-today-referrals',  r.today.emergencies);   // emergencies today
    setKpiValue('kpi-month-consults',   r.month.consultations);
    setKpiValue('kpi-ape-completed',    r.ape.completed);
    const apeBar = document.getElementById('kpi-ape-bar');
    if (apeBar) apeBar.style.setProperty('--fill', r.ape.percent + '%');
    const apeText = document.getElementById('kpi-ape-percent');
    if (apeText) apeText.textContent = r.ape.percent + '%';
    const apeSub = document.getElementById('kpi-ape-sub');
    if (apeSub) apeSub.textContent = `${r.ape.completed} of ${r.ape.total} employees completed`;
  }
  function setKpiValue(id, v) {
    const el = document.getElementById(id);
    if (el) el.textContent = (v == null ? '—' : v);
  }

  // ── Top illnesses (donut legend) ────────────────────────────
  async function loadIllness() {
    const r = await API.get(cfg.endpoints.reports, { report: 'illness' });
    const total = r.top_illnesses.reduce((s, x) => s + Number(x.visits), 0) || 1;
    const list  = document.getElementById('illness-legend');
    if (!list) return;
    list.innerHTML = '';
    const palette = ['var(--blue-500)','var(--amber)','var(--red)','var(--green)','var(--border-2)','var(--blue-700)'];
    r.top_illnesses.forEach((row, idx) => {
      const pct = Math.round(row.visits * 100 / total);
      list.insertAdjacentHTML('beforeend', `
        <div class="legend-item">
          <div class="legend-dot" style="background:${palette[idx % palette.length]}"></div>
          <strong>${escapeHtml(row.diagnosis)}</strong>
          <span class="legend-pct">${pct}%</span>
        </div>
      `);
    });
  }

  // ── Consultations by department ────────────────────────────
  async function loadByDepartment() {
    const r = await API.get(cfg.endpoints.reports, { report: 'by-department' });
    const tbody = document.querySelector('#dept-table tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    r.departments.forEach(d => {
      const visits = Number(d.visits || 0);
      let trendCls = 'green', trendLbl = 'Low';
      if (visits >= 80)      { trendCls = 'red';   trendLbl = 'High'; }
      else if (visits >= 40) { trendCls = 'amber'; trendLbl = 'Moderate'; }
      else if (visits >= 15) { trendCls = 'blue';  trendLbl = 'Stable'; }
      tbody.insertAdjacentHTML('beforeend', `
        <tr>
          <td><span class="td-name">${escapeHtml(d.dept_name)}</span></td>
          <td>${visits}</td><td>${Number(d.referrals || 0)}</td>
          <td><span class="badge ${trendCls}">${trendLbl}</span></td>
        </tr>
      `);
    });
  }

  // ── Inventory ───────────────────────────────────────────────
  async function loadInventory() {
    const r = await API.get(cfg.endpoints.medicines);
    const wrap = document.getElementById('inventory-list');
    if (!wrap) return;
    wrap.innerHTML = '';
    r.medicines.slice(0, 6).forEach(m => {
      const min = Number(m.min_stock || 1);
      const cur = Number(m.current_stock || 0);
      const pct = Math.max(2, Math.min(100, Math.round(cur * 100 / Math.max(min * 4, 1))));
      const colors = {
        critical:    { fill: 'var(--red)',   badge: 'red',   label: 'Critical', cls: 'low'  },
        low:         { fill: 'var(--amber)', badge: 'amber', label: 'Low',      cls: 'warn' },
        out:         { fill: 'var(--red)',   badge: 'red',   label: 'Out',      cls: 'low'  },
        near_expiry: { fill: 'var(--amber)', badge: 'amber', label: 'Near Expiry', cls: 'warn' },
        good:        { fill: 'var(--green)', badge: 'green', label: 'Good',     cls: 'ok'   },
      };
      const c = colors[m.stock_status] || colors.good;
      wrap.insertAdjacentHTML('beforeend', `
        <div class="med-row">
          <div class="med-icon">${escapeHtml(m.medicine_name.charAt(0))}</div>
          <div class="med-info">
            <strong>${escapeHtml(m.medicine_name)} ${escapeHtml(m.dosage_strength)}</strong>
            <small>Min. stock: ${min} ${escapeHtml(m.unit)} · Expires ${fmtDate(m.expiry_date)}</small>
          </div>
          <div class="med-stock">
            <div class="med-count ${c.cls}">${cur}</div>
            <div class="stock-bar">
              <div class="stock-fill" style="--fill:${pct}%;--fill-color:${c.fill}"></div>
            </div>
            <span class="badge ${c.badge}" style="margin-top:4px; font-size:10px;">${c.label}</span>
          </div>
        </div>
      `);
    });
  }

  // ── Referrals ───────────────────────────────────────────────
  async function loadReferrals() {
    const r = await API.get(cfg.endpoints.reports, { report: 'referrals' });
    const wrap = document.getElementById('referrals-list');
    if (!wrap) return;
    wrap.innerHTML = '';
    r.referrals.slice(0, 6).forEach(rf => {
      const isUrgent = rf.referral_type === 'emergency' || rf.referral_type === 'hospital';
      const statusCls = rf.status === 'completed' ? 'green'
                      : rf.status === 'cancelled' ? 'red' : 'blue';
      wrap.insertAdjacentHTML('beforeend', `
        <div class="referral-card${isUrgent ? ' urgent' : ''}">
          <h4>${escapeHtml(rf.first_name + ' ' + rf.last_name)} — ${escapeHtml(rf.diagnosis || 'Consultation')}</h4>
          <p>Referred to: ${escapeHtml(rf.referred_to)}</p>
          <div class="ref-meta">
            <span class="badge ${isUrgent ? 'red' : 'blue'}">${fmtDate(rf.referral_date)}</span>
            <span class="badge ${statusCls}">${escapeHtml(rf.status)}</span>
          </div>
        </div>
      `);
    });
  }

  // ── APE compliance ──────────────────────────────────────────
  async function loadApe() {
    const r = await API.get(cfg.endpoints.ape, { year: new Date().getFullYear() });
    const tbody = document.querySelector('#ape-table tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    r.by_department.forEach(d => {
      const cls = d.rate >= 90 ? 'green' : d.rate >= 80 ? 'amber' : 'red';
      tbody.insertAdjacentHTML('beforeend', `
        <tr>
          <td>${escapeHtml(d.dept_name)}</td>
          <td>${d.done}</td><td>${d.pending}</td>
          <td><span class="badge ${cls}">${d.rate}%</span></td>
        </tr>
      `);
    });
    const head = document.getElementById('ape-overall-percent');
    if (head) head.textContent = r.totals.percent + '%';
    const sub = document.getElementById('ape-overall-sub');
    if (sub) sub.textContent = `${r.totals.completed} of ${r.totals.total_employees} employees completed`;
    const fill = document.getElementById('ape-overall-bar');
    if (fill) fill.style.setProperty('--fill', r.totals.percent + '%');
  }

  // ── Audit log (today) ──────────────────────────────────────
  async function loadAudit() {
    const r = await API.get(cfg.endpoints.audit, { day: new Date().toISOString().slice(0, 10) });
    const tbody = document.querySelector('#audit-table tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    r.entries.slice(0, 30).forEach(a => {
      let typeBadge = 'blue', typeLbl = a.table_affected;
      if (a.table_affected === 'consultation')         { typeBadge = 'blue';  typeLbl = 'Consultation'; }
      else if (a.table_affected === 'medicine')        { typeBadge = 'navy';  typeLbl = 'Inventory'; }
      else if (a.table_affected === 'medicine_dispensed'){ typeBadge = 'green'; typeLbl = 'Dispense'; }
      else if (a.table_affected === 'referral')        { typeBadge = 'amber'; typeLbl = 'Referral'; }
      else if (a.table_affected === 'user_account')    { typeBadge = 'red';   typeLbl = 'Account'; }

      const time = (a.action_timestamp || '').slice(11, 16);
      const actor = a.actor_admin_name || a.actor_user_name || a.actor_username || 'System';
      const summary = formatAuditSummary(a);
      tbody.insertAdjacentHTML('beforeend', `
        <tr>
          <td><span class="log-time">${escapeHtml(time)}</span></td>
          <td><span class="badge ${typeBadge}">${escapeHtml(typeLbl)}</span></td>
          <td>—</td>
          <td>${escapeHtml(summary)}</td>
          <td>${escapeHtml(actor)}</td>
          <td><span class="badge green">${escapeHtml(a.action_type)}</span></td>
        </tr>
      `);
    });
  }

  function formatAuditSummary(a) {
    let oldVal = {}, newVal = {};
    try { oldVal = a.old_value ? JSON.parse(a.old_value) : {}; } catch {}
    try { newVal = a.new_value ? JSON.parse(a.new_value) : {}; } catch {}
    const fields = Object.keys({ ...oldVal, ...newVal });
    if (a.action_type === 'INSERT') {
      return `${a.table_affected} #${a.record_id} created`;
    }
    if (a.action_type === 'UPDATE') {
      return `${a.table_affected} #${a.record_id} updated (${fields.join(', ')})`;
    }
    return `${a.table_affected} #${a.record_id} ${a.action_type.toLowerCase()}`;
  }

  // ── Clinic settings card ───────────────────────────────────
  async function loadSettings() {
    let r;
    try { r = await API.get(cfg.endpoints.settings); }
    catch { return; }
    const s = r.settings || {};
    const set = (id, v) => { const el = document.getElementById(id); if (el && v != null) el.value = v; };
    set('clinic_name',    s.clinic_name);
    set('clinic_address', s.clinic_address);
    set('opening_time',   s.opening_time);
    set('closing_time',   s.closing_time);
    set('contact_email',  s.contact_email);
  }

  window.adminSaveSettings = async function () {
    const payload = {
      clinic_name:    document.getElementById('clinic_name').value,
      clinic_address: document.getElementById('clinic_address').value,
      opening_time:   document.getElementById('opening_time').value,
      closing_time:   document.getElementById('closing_time').value,
      contact_email:  document.getElementById('contact_email').value,
    };
    try {
      await API.post(cfg.endpoints.settings, payload);
      showToast('Clinic settings saved successfully.');
    } catch (err) {
      showToast('Save failed: ' + (err.body && err.body.error || err.message));
    }
  };

  window.adminRequestRestock = async function (medicineId) {
    if (!medicineId) return showToast('No medicine selected.');
    const qty = parseInt(prompt('Quantity to add to stock?', '50'), 10);
    if (!Number.isFinite(qty) || qty <= 0) return;
    const remarks = prompt('Remarks (optional):', 'Manual restock');
    try {
      await API.post(cfg.endpoints.restock, { medicine_id: medicineId, quantity: qty, remarks });
      showToast('Stock added. Inventory updated.');
      loadInventory();
    } catch (err) {
      showToast('Restock failed: ' + (err.body && err.body.error || err.message));
    }
  };
})();
