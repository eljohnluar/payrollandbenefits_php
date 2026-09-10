/* app.js – Vanilla JS for Payroll & Benefits Management System */

// ── Toast ─────────────────────────────────────────────
function showToast(msg, type = 'success') {
  const c = document.getElementById('toastContainer');
  if (!c) return;
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.textContent = msg;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3500);
}

// ── Modal helpers ──────────────────────────────────────
function openModal(id) {
  const m = document.getElementById(id);
  if (m) { m.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) { m.style.display = 'none'; document.body.style.overflow = ''; }
}
// Close modal on backdrop click
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.style.display = 'none';
    document.body.style.overflow = '';
  }
});

// ── Notification dropdown ──────────────────────────────
const notifBtn = document.getElementById('notifBtn');
const notifDD  = document.getElementById('notifDropdown');
if (notifBtn && notifDD) {
  notifBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    notifDD.style.display = notifDD.style.display === 'none' ? 'block' : 'none';
  });
  document.addEventListener('click', () => { if (notifDD) notifDD.style.display = 'none'; });
}
function markAllRead() {
  fetch('?action=mark_notif_read', { method: 'POST' })
    .then(() => { if (notifDD) notifDD.querySelectorAll('[style*="rgba"]').forEach(el => el.style.background = 'transparent'); });
}

// ── Tab switcher ───────────────────────────────────────
function switchTab(tabGroup, tabId) {
  document.querySelectorAll(`[data-tab-group="${tabGroup}"]`).forEach(el => {
    el.style.display = el.dataset.tab === tabId ? '' : 'none';
  });
  document.querySelectorAll(`[data-tab-btn-group="${tabGroup}"]`).forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tabTarget === tabId);
  });
}

// ── Employee search/filter ─────────────────────────────
function filterTable(inputId, tableId, colIndexes) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  const val = input.value.toLowerCase();
  table.querySelectorAll('tbody tr').forEach(row => {
    const text = colIndexes.map(i => (row.cells[i] ? row.cells[i].textContent : '')).join(' ').toLowerCase();
    row.style.display = text.includes(val) ? '' : 'none';
  });
}

// ── E-Wallet account validation ────────────────────────
function validateEWalletAccount(provider, account) {
  const mobileProviders = ['GCash', 'Maya', 'PayMaya'];
  const bankProviders   = ['Bank', 'Company Bank'];
  if (provider === 'Cash') return { valid: true, msg: '' };
  if (!account) return { valid: false, msg: 'Account number is required.' };
  if (mobileProviders.includes(provider)) {
    const clean = account.replace(/\D/g, '');
    if (clean.length !== 11 || !clean.startsWith('09'))
      return { valid: false, msg: 'Must be 11 digits starting with 09.' };
    return { valid: true, msg: '✓ Valid mobile number' };
  }
  if (bankProviders.includes(provider)) {
    const clean = account.replace(/\D/g, '');
    if (clean.length < 10 || clean.length > 16)
      return { valid: false, msg: 'Account number must be 10–16 digits.' };
    return { valid: true, msg: '✓ Valid account number' };
  }
  return { valid: true, msg: '' };
}

function onProviderChange(providerSel, accountInput, feedbackEl) {
  const p = providerSel.value;
  if (p === 'Cash') {
    accountInput.value = '';
    accountInput.disabled = true;
    if (feedbackEl) feedbackEl.textContent = '';
  } else {
    accountInput.disabled = false;
    accountInput.placeholder = ['GCash','Maya','PayMaya'].includes(p) ? '09XXXXXXXXX' : 'Account Number';
  }
}
function onAccountInput(providerSel, accountInput, feedbackEl) {
  const res = validateEWalletAccount(providerSel.value, accountInput.value);
  if (feedbackEl) {
    feedbackEl.textContent = res.msg;
    feedbackEl.style.color = res.valid ? 'var(--success)' : 'var(--danger)';
  }
}

// ── Payroll computation (client-side mirror) ───────────
function computeSSS(salary) {
  if (salary < 5000)  return { ee: 225,  er: 475 };
  if (salary < 10000) return { ee: 450,  er: 950 };
  if (salary < 15000) return { ee: 675,  er: 1425 };
  if (salary < 20000) return { ee: 900,  er: 1900 };
  return { ee: 900, er: Math.min(salary * 0.095, 1900) };
}
function computePhilHealth(salary) {
  const base = Math.min(salary, 100000);
  return { ee: base * 0.02, er: base * 0.02 };
}
function computePagIBIG(salary) {
  return { ee: salary >= 5000 ? 100 : 50, er: 100 };
}
function computeWithholdingTax(grossPay, totalContrib) {
  const taxable = grossPay - totalContrib;
  const annual  = taxable * 12;
  let annualTax = 0;
  if (annual <= 250000)       annualTax = 0;
  else if (annual <= 400000)  annualTax = (annual - 250000) * 0.15;
  else if (annual <= 800000)  annualTax = 22500  + (annual - 400000) * 0.20;
  else if (annual <= 2000000) annualTax = 102500 + (annual - 800000) * 0.25;
  else if (annual <= 8000000) annualTax = 402500 + (annual - 2000000) * 0.30;
  else                        annualTax = 2202500 + (annual - 8000000) * 0.35;
  return Math.round(annualTax / 12 * 100) / 100;
}

// ── Currency formatting ────────────────────────────────
function formatPHP(amount) {
  return '₱' + parseFloat(amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ── Attendance bulk actions ────────────────────────────
function markAllPresent() {
  document.querySelectorAll('.att-status-select').forEach(sel => {
    sel.value = 'P';
    sel.dispatchEvent(new Event('change'));
  });
  showToast('All employees marked as Present');
}
function clearAllAttendance() {
  if (!confirm('Clear all attendance for this date?')) return;
  document.querySelectorAll('.att-status-select').forEach(sel => { sel.value = ''; });
  showToast('Attendance cleared', 'warning');
}

// ── Toggle expand payroll row ──────────────────────────
function togglePayrollRow(empId) {
  const row = document.getElementById('pr-detail-' + empId);
  if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}

// ── Toggle show/hide account number ───────────────────
function toggleAccountVisibility(btnId, spanId, fullNumber) {
  const span = document.getElementById(spanId);
  const btn  = document.getElementById(btnId);
  if (!span || !btn) return;
  if (span.dataset.shown === '1') {
    span.textContent = '•••••••' + fullNumber.slice(-4);
    span.dataset.shown = '0';
    btn.textContent = 'Show';
  } else {
    span.textContent = fullNumber;
    span.dataset.shown = '1';
    btn.textContent = 'Hide';
  }
}

// ── AI claim submission (3-state UI) ──────────────────
function runClaimAI(form, resultDivId, processingDivId, formDivId) {
  const formDiv  = document.getElementById(formDivId);
  const procDiv  = document.getElementById(processingDivId);
  const resDiv   = document.getElementById(resultDivId);
  if (!formDiv || !procDiv || !resDiv) return;

  const desc     = form.description.value.toLowerCase();
  const amount   = parseFloat(form.amount.value);
  const category = form.category.value;

  // Category keywords
  const keywords = {
    'Transportation': ['grab', 'taxi', 'uber', 'jeep', 'bus', 'mrt', 'lrt', 'toll', 'parking', 'ride'],
    'Meal Allowance': ['meal', 'lunch', 'dinner', 'food', 'resto', 'restaurant', 'breakfast', 'snack', 'coffee'],
    'Medical':        ['medical', 'hospital', 'clinic', 'doctor', 'medicine', 'pharmacy', 'health', 'consult'],
    'Office Supplies':['supplies', 'paper', 'ink', 'printer', 'pen', 'notebook', 'office', 'stationery'],
    'Training':       ['training', 'seminar', 'workshop', 'conference', 'certification', 'course', 'learning'],
    'Overtime':       ['overtime', 'ot', 'extra hours', 'additional work'],
  };
  const maxAmounts = {
    'Transportation': 1000, 'Meal Allowance': 500, 'Medical': 5000,
    'Office Supplies': 2000, 'Training': 10000, 'Overtime': 300,
  };

  const catKeywords = keywords[category] || [];
  const matches = catKeywords.filter(k => desc.includes(k)).length;
  let confidence = Math.min(55 + matches * 12 + (desc.length > 20 ? 5 : 0), 99);

  const suspicious = ['personal','home','vacation','shopping','gaming'];
  const maxAmt = maxAmounts[category] || 1000;
  let fraudScore = 5;
  if (amount > maxAmt * 0.9) fraudScore += 20;
  if (amount > maxAmt)       fraudScore += 30;
  if (desc.length < 10)      fraudScore += 25;
  suspicious.forEach(w => { if (desc.includes(w)) fraudScore += 20; });
  fraudScore = Math.min(fraudScore, 99);

  const autoApproved = confidence >= 80 && fraudScore < 20 && amount <= maxAmt;

  // Show processing
  formDiv.style.display = 'none';
  procDiv.style.display = 'flex';

  setTimeout(() => {
    procDiv.style.display = 'none';
    resDiv.style.display  = 'block';

    const icon    = document.getElementById('ai-icon');
    const title   = document.getElementById('ai-title');
    const confBar = document.getElementById('ai-conf-bar');
    const fraudBar= document.getElementById('ai-fraud-bar');
    const confPct = document.getElementById('ai-conf-pct');
    const fraudPct= document.getElementById('ai-fraud-pct');

    if (icon)  icon.innerHTML = autoApproved
      ? '<span style="font-size:48px;color:var(--success)">✓</span>'
      : '<span style="font-size:48px;color:var(--warning)">⚠</span>';
    if (title) title.textContent = autoApproved ? 'Claim Auto-Approved!' : 'Pending Manual Review';
    if (confBar)  confBar.style.width  = confidence + '%';
    if (fraudBar) {
      fraudBar.style.width = fraudScore + '%';
      fraudBar.classList.toggle('danger', fraudScore >= 40);
      fraudBar.classList.toggle('warning', fraudScore >= 20 && fraudScore < 40);
    }
    if (confPct)  confPct.textContent  = confidence + '%';
    if (fraudPct) fraudPct.textContent = fraudScore + '%';
  }, 2200);
}

// ── Leave conversion amount calculator ────────────────
function calcLeaveAmount(daysInputId, amountInputId, rate) {
  const days = parseFloat(document.getElementById(daysInputId)?.value || 0);
  const el   = document.getElementById(amountInputId);
  if (el) el.value = formatPHP(days * rate);
}

// ── Salary rate auto-compute ───────────────────────────
function computeRates(salaryId, dailyId, hourlyId) {
  const salary = parseFloat(document.getElementById(salaryId)?.value || 0);
  const daily  = salary / 22;
  const hourly = daily  / 8;
  if (document.getElementById(dailyId))  document.getElementById(dailyId).value  = daily.toFixed(2);
  if (document.getElementById(hourlyId)) document.getElementById(hourlyId).value = hourly.toFixed(2);
}

// ── Print payslip ──────────────────────────────────────
function printPayslip() { window.print(); }

// ── Select filter change → submit form ────────────────
function submitOnChange(formId) {
  document.getElementById(formId)?.submit();
}

// ── Generic search (client-side row filter) ────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-search-table]').forEach(input => {
    input.addEventListener('input', () => {
      const tbl = document.getElementById(input.dataset.searchTable);
      if (!tbl) return;
      const val = input.value.toLowerCase();
      tbl.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
      });
    });
  });

  document.querySelectorAll('[data-filter-col]').forEach(sel => {
    sel.addEventListener('change', () => {
      const tbl = document.getElementById(sel.dataset.filterTable);
      const col = parseInt(sel.dataset.filterCol);
      if (!tbl) return;
      const val = sel.value.toLowerCase();
      tbl.querySelectorAll('tbody tr').forEach(row => {
        const cell = row.cells[col];
        row.style.display = (!val || (cell && cell.textContent.toLowerCase().includes(val))) ? '' : 'none';
      });
    });
  });
});
