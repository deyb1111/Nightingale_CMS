/* ============================================================
   NIGHTINGALE — Nurse Dashboard
============================================================ */
(function () {
  const cfg = window.APP_CONFIG;
  let activeQueueRow = null;

  document.addEventListener('nightingale:session', () => {
    Promise.all([
      loadQueue(),
      loadMedicines(),
      loadKpis(),
    ]).catch(err => console.error('[nurse] load error', err));
  });

  // ── Today's queue ───────────────────────────────────────────
  async function loadQueue() {
    const r = await API.get(cfg.endpoints.queue, { date: new Date().toISOString().slice(0, 10) });
    const wrap = document.getElementById('queue-list');
    if (!wrap) return;
    wrap.innerHTML = '';
    r.queue.forEach(q => {
      const initials = (q.first_name?.charAt(0) || '?') + (q.last_name?.charAt(0) || '?');
      const item = document.createElement('div');
      item.className = 'queue-item';
      item.dataset.queueId = q.queue_id;
      item.dataset.employeeId = q.employee_id;
      item.dataset.consultationId = q.consultation_id || '';
      item.innerHTML = `
        <div class="patient-avatar">${escapeHtml(initials.toUpperCase())}</div>
        <div class="patient-info">
          <strong>${escapeHtml(q.first_name + ' ' + q.last_name)}</strong>
          <small>${escapeHtml(q.dept_name)} · #${q.employee_no}</small>
        </div>
        <div class="patient-meta">
          ${statusBadge(q.status)}
          <small>${fmtTime(q.time_in)}</small>
        </div>
      `;
      item.addEventListener('click', () => selectQueueRow(item, q));
      wrap.appendChild(item);
    });

    const counter = document.getElementById('queue-count');
    if (counter) counter.textContent = r.queue.length;
  }

  function selectQueueRow(item, q) {
    document.querySelectorAll('.queue-item').forEach(i => i.classList.remove('active-patient'));
    item.classList.add('active-patient');
    activeQueueRow = q;

    const detailHead = document.getElementById('selected-patient-name');
    if (detailHead) detailHead.textContent = `${q.first_name} ${q.last_name}`;
    const detailMeta = document.getElementById('selected-patient-meta');
    if (detailMeta) detailMeta.textContent = `${q.dept_name} · ${q.employee_no} · Q${q.queue_number}`;

    if (q.consultation_id) loadConsultation(q.consultation_id);
  }

  async function loadConsultation(cid) {
    const r = await API.get(cfg.endpoints.consultation, { id: cid });
    const c = r.consultation;
    setVal('vital-bps', c.bp_systolic);
    setVal('vital-bpd', c.bp_diastolic);
    setVal('vital-temp', c.temperature);
    setVal('vital-pulse', c.pulse_rate);
    setVal('vital-resp', c.resp_rate);
    setVal('vital-o2', c.o2_saturation);
    setVal('vital-weight', c.weight_kg);
    setVal('chief-complaint', c.chief_complaint);
    setVal('diagnosis', c.diagnosis);
    setVal('nurse-notes', c.nurse_notes);

    const list = document.getElementById('dispense-list');
    if (list) {
      list.innerHTML = '';
      c.dispenses.forEach(d => {
        list.insertAdjacentHTML('beforeend', `
          <div class="med-row">
            <div class="med-icon">${escapeHtml(d.medicine_name.charAt(0))}</div>
            <div class="med-info">
              <strong>${escapeHtml(d.medicine_name)} ${escapeHtml(d.dosage_strength)}</strong>
              <small>${escapeHtml(d.dosage_instructions || '')}</small>
            </div>
            <div class="med-stock"><strong>${d.quantity}</strong><small>${escapeHtml(d.unit)}</small></div>
          </div>
        `);
      });
    }
  }

  function setVal(id, v) {
    const el = document.getElementById(id);
    if (el) el.value = (v == null ? '' : v);
  }

  // ── Medicines for the dispense dropdown ─────────────────────
  async function loadMedicines() {
    const r = await API.get(cfg.endpoints.medicines);
    const sel = document.getElementById('dispense-medicine-id');
    if (!sel) return;
    sel.innerHTML = '<option value="">— select medicine —</option>';
    r.medicines.forEach(m => {
      sel.insertAdjacentHTML('beforeend',
        `<option value="${m.medicine_id}" ${m.current_stock <= 0 ? 'disabled' : ''}>
           ${escapeHtml(m.medicine_name)} ${escapeHtml(m.dosage_strength)} (stock: ${m.current_stock})
         </option>`);
    });
  }

  // ── Topbar KPIs ─────────────────────────────────────────────
  async function loadKpis() {
    try {
      const r = await API.get(cfg.endpoints.reports, { report: 'kpis' });
      const t = document.getElementById('nurse-kpi-today');
      const m = document.getElementById('nurse-kpi-month');
      const e = document.getElementById('nurse-kpi-emergencies');
      if (t) t.textContent = r.today.consultations;
      if (m) m.textContent = r.month.consultations;
      if (e) e.textContent = r.today.emergencies;
    } catch {}
  }

  // ── Save vitals ─────────────────────────────────────────────
  window.saveVitals = async function () {
    if (!activeQueueRow || !activeQueueRow.consultation_id) {
      return showToast('Open a consultation first.');
    }
    const payload = {
      consultation_id: activeQueueRow.consultation_id,
      bp_systolic:  +document.getElementById('vital-bps').value || null,
      bp_diastolic: +document.getElementById('vital-bpd').value || null,
      temperature:  +document.getElementById('vital-temp').value || null,
      pulse_rate:   +document.getElementById('vital-pulse').value || null,
      resp_rate:    +document.getElementById('vital-resp').value || null,
      o2_saturation:+document.getElementById('vital-o2').value || null,
      weight_kg:    +document.getElementById('vital-weight').value || null,
    };
    try {
      await API.post(cfg.endpoints.vitals, payload);
      showToast('Vitals saved.');
    } catch (err) {
      showToast('Save failed: ' + (err.body && err.body.error || err.message));
    }
  };

  // ── Open consultation ───────────────────────────────────────
  window.openConsultation = async function () {
    if (!activeQueueRow) return showToast('Select a patient first.');
    const chief = document.getElementById('chief-complaint').value.trim();
    if (!chief) return showToast('Chief complaint required.');
    const caseType = document.querySelector('input[name="case_type"]:checked')?.value || 'illness';
    try {
      const r = await API.post(cfg.endpoints.consultation, {
        action: 'open',
        employee_id: activeQueueRow.employee_id,
        queue_number: activeQueueRow.queue_number,
        chief_complaint: chief,
        case_type: caseType,
      });
      activeQueueRow.consultation_id = r.consultation_id;
      showToast('Consultation opened.');
      loadQueue();
    } catch (err) {
      showToast('Open failed: ' + (err.body && err.body.error || err.message));
    }
  };

  // ── Close consultation ──────────────────────────────────────
  window.closeConsultation = async function () {
    if (!activeQueueRow || !activeQueueRow.consultation_id) {
      return showToast('Open a consultation first.');
    }
    const diagnosis = document.getElementById('diagnosis').value.trim();
    const notes     = document.getElementById('nurse-notes').value.trim();
    const workStatus = document.getElementById('work-status').value;
    try {
      await API.post(cfg.endpoints.consultation, {
        action: 'close',
        consultation_id: activeQueueRow.consultation_id,
        diagnosis, nurse_notes: notes, work_status: workStatus,
      });
      showToast('Consultation closed.');
      loadQueue();
    } catch (err) {
      showToast('Close failed: ' + (err.body && err.body.error || err.message));
    }
  };

  // ── Dispense ───────────────────────────────────────────────
  window.dispenseMedicine = async function () {
    if (!activeQueueRow || !activeQueueRow.consultation_id) {
      return showToast('Open a consultation first.');
    }
    const mid = parseInt(document.getElementById('dispense-medicine-id').value, 10);
    const qty = parseInt(document.getElementById('dispense-qty').value, 10);
    const instr = document.getElementById('dispense-instructions').value;
    if (!mid || !qty) return showToast('Choose a medicine and quantity.');
    try {
      await API.post(cfg.endpoints.dispense, {
        consultation_id: activeQueueRow.consultation_id,
        medicine_id: mid,
        quantity: qty,
        dosage_instructions: instr,
      });
      showToast('Medicine dispensed.');
      loadConsultation(activeQueueRow.consultation_id);
      loadMedicines();
    } catch (err) {
      showToast('Dispense failed: ' + (err.body && err.body.error || err.message));
    }
  };

  // ── Referral ───────────────────────────────────────────────
  window.createReferral = async function () {
    if (!activeQueueRow || !activeQueueRow.consultation_id) {
      return showToast('Open a consultation first.');
    }
    const type   = document.getElementById('ref-type').value;
    const to     = document.getElementById('ref-to').value;
    const reason = document.getElementById('ref-reason').value;
    if (!to || !reason) return showToast('Referred-to and reason required.');
    try {
      await API.post(cfg.endpoints.referral, {
        consultation_id: activeQueueRow.consultation_id,
        referral_type: type,
        referred_to: to,
        reason,
      });
      showToast('Referral issued.');
    } catch (err) {
      showToast('Referral failed: ' + (err.body && err.body.error || err.message));
    }
  };
})();
