/* ============================================================
   NIGHTINGALE — Patient Dashboard
============================================================ */
(function () {
  const cfg = window.APP_CONFIG;

  document.addEventListener('nightingale:session', () => {
    Promise.all([
      loadProfile(),
      loadApe(),
    ]).catch(err => console.error('[patient] load error', err));
  });

  async function loadProfile() {
    const r = await API.get(cfg.endpoints.patients, { mode: 'me' });
    const p = r.profile || {};
    setText('p-fullname', `${p.first_name || ''} ${p.last_name || ''}`);
    setText('p-employee-no', p.employee_no);
    setText('p-dept',        p.dept_name);
    setText('p-blood',       p.blood_type);
    setText('p-allergies',   p.allergies || 'None reported');
    setText('p-emergency',   `${p.emergency_contact || '—'} · ${p.emergency_phone || '—'}`);

    // Stats card
    setText('p-stat-visits',     r.stats?.total_visits ?? 0);
    setText('p-stat-emergencies',r.stats?.emergencies ?? 0);
    setText('p-stat-referrals',  r.stats?.referrals ?? 0);
    setText('p-stat-last',       r.stats?.last_visit ? fmtDate(r.stats.last_visit) : '—');

    // Last vitals
    if (r.last_vitals) {
      setText('p-last-bp',   `${r.last_vitals.bp_systolic || '—'}/${r.last_vitals.bp_diastolic || '—'}`);
      setText('p-last-temp', r.last_vitals.temperature ? r.last_vitals.temperature + ' °C' : '—');
      setText('p-last-weight', r.last_vitals.weight_kg ? r.last_vitals.weight_kg + ' kg' : '—');
    }

    // Today queue card
    const q = r.today_queue;
    const qCard = document.getElementById('p-queue-card');
    if (qCard) {
      if (q && q.queue_date === new Date().toISOString().slice(0, 10)) {
        qCard.classList.remove('hidden');
        setText('p-queue-number', q.queue_number);
        setText('p-queue-status', (q.status || '').replace('_',' '));
      } else {
        qCard.classList.add('hidden');
      }
    }

    // History table
    const tbody = document.querySelector('#history-table tbody');
    if (tbody) {
      tbody.innerHTML = '';
      r.history.forEach(h => {
        tbody.insertAdjacentHTML('beforeend', `
          <tr>
            <td>${fmtDate(h.consult_date)} ${fmtTime(h.time_start)}</td>
            <td>${caseTypeBadge(h.case_type)}</td>
            <td>${escapeHtml(h.diagnosis || '—')}</td>
            <td>${escapeHtml((h.work_status || '').replace('_',' '))}</td>
            <td>${h.bp_systolic ? `${h.bp_systolic}/${h.bp_diastolic}` : '—'}</td>
          </tr>
        `);
      });
    }
  }

  async function loadApe() {
    const r = await API.get(cfg.endpoints.ape);
    const tbody = document.querySelector('#patient-ape-table tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    r.records.forEach(a => {
      tbody.insertAdjacentHTML('beforeend', `
        <tr>
          <td>${a.exam_year}</td>
          <td>${fmtDate(a.exam_date)}</td>
          <td>${a.bp_systolic || '—'}/${a.bp_diastolic || '—'}</td>
          <td>${a.weight_kg ? a.weight_kg + ' kg' : '—'}</td>
          <td>${a.bmi || '—'}</td>
          <td><span class="badge ${a.status === 'completed' || a.status === 'cleared' ? 'green' : 'amber'}">${escapeHtml(a.status)}</span></td>
        </tr>
      `);
    });
  }

  function setText(id, v) {
    const el = document.getElementById(id);
    if (el) el.textContent = (v == null || v === '') ? '—' : v;
  }
})();
