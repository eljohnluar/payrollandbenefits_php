<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Attendance';
$currentPage = 'attendance';
$pdo = getDB();

$selectedDate = $_GET['selected_date'] ?? date('Y-m-d');
$currentMonth = date('Y-m', strtotime($selectedDate));
$msg = $_GET['msg'] ?? '';

$error = $_GET['error'] ?? null;

// Fetch Active Employees
$activeEmps = $pdo->query("SELECT id, code, first_name, last_name, department FROM employees WHERE status IN ('Active', 'On Leave') ORDER BY first_name")->fetchAll();

// Fetch today's logs
$stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE log_date = ?");
$stmt->execute([$selectedDate]);
$logsRaw = $stmt->fetchAll();
$logs = [];
$statPresent = 0; $statAbsent = 0; $statOT = 0;
foreach($logsRaw as $l) {
    $logs[$l['employee_id']] = $l;
    if ($l['status'] === 'P' || $l['status'] === 'OT') $statPresent++;
    if ($l['status'] === 'A') $statAbsent++;
    if ($l['status'] === 'OT') $statOT++;
}

// Fetch monthly summary
$stmtM = $pdo->prepare("
    SELECT a.employee_id, e.first_name, e.last_name, e.department,
           SUM(CASE WHEN a.status='P' THEN 1 ELSE 0 END) as p_days,
           SUM(CASE WHEN a.status='H' THEN 1 ELSE 0 END) as h_days,
           SUM(CASE WHEN a.status='A' THEN 1 ELSE 0 END) as a_days,
           SUM(CASE WHEN a.status='OT' THEN 1 ELSE 0 END) as ot_days,
           SUM(a.ot_hours) as tot_ot
    FROM attendance_logs a
    JOIN employees e ON a.employee_id = e.id
    WHERE DATE_FORMAT(a.log_date, '%Y-%m') = ?
    GROUP BY a.employee_id
");
$stmtM->execute([$currentMonth]);
$monthly = $stmtM->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header" style="align-items:center;">
      <div>
        <h1>Attendance</h1>
        <p>Record daily attendance and view monthly summaries</p>
      </div>
      <form method="GET" style="display:flex; gap:10px;">
        <input type="hidden" name="page" value="attendance">
        <input type="date" name="selected_date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
        <button type="button" class="btn btn-primary" onclick="document.getElementById('attSaveForm').submit()">Save Attendance</button>
      </form>
    </div>

    <?php if (isset($error)): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><script>document.addEventListener('DOMContentLoaded', ()=>showToast('<?= htmlspecialchars($msg) ?>'));</script><?php endif; ?>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Employees</div>
        <div class="stat-value"><?= count($activeEmps) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Present (inc. OT)</div>
        <div class="stat-value text-main" style="color:var(--success)"><?= $statPresent ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Absences</div>
        <div class="stat-value" style="color:var(--danger)"><?= $statAbsent ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">On Overtime</div>
        <div class="stat-value" style="color:var(--primary)"><?= $statOT ?></div>
      </div>
    </div>

    <!-- Bulk Actions -->
    <div class="quick-actions">
        <form method="POST" action="<?= BASE_URL ?>/api/attendance.php" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="mark_all_present">
            <input type="hidden" name="selected_date" value="<?= htmlspecialchars($selectedDate) ?>">
            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Mark all active employees as Present?')">Mark All Present</button>
        </form>
        <a href="?page=attendance&selected_date=<?= date('Y-m-d', strtotime($selectedDate . ' -1 day')) ?>" class="btn btn-secondary btn-sm">Copy Previous Day</a>
        <form method="POST" action="<?= BASE_URL ?>/api/attendance.php" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="clear_attendance">
            <input type="hidden" name="selected_date" value="<?= htmlspecialchars($selectedDate) ?>">
            <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="return confirm('Clear attendance for this date?')">Clear All</button>
        </form>
    </div>

    <!-- Tabs -->
    <div style="margin-bottom:16px; border-bottom:1px solid var(--border); display:flex; gap:16px;">
        <button class="nav-link active" data-tab-btn-group="att" data-tab-target="daily" onclick="switchTab('att', 'daily')" style="width:auto;border-radius:0;border-bottom:2px solid var(--primary);padding-bottom:12px;background:none;">Daily Entry</button>
        <button class="nav-link" data-tab-btn-group="att" data-tab-target="monthly" onclick="switchTab('att', 'monthly')" style="width:auto;border-radius:0;border-bottom:2px solid transparent;padding-bottom:12px;background:none;">Monthly Summary (<?= date('M Y', strtotime($selectedDate)) ?>)</button>
    </div>

    <!-- Daily Entry Tab -->
    <div data-tab-group="att" data-tab="daily">
        <div class="card">
            <form id="attSaveForm" method="POST" action="<?= BASE_URL ?>/api/attendance.php">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="action" value="save_attendance">
                <input type="hidden" name="selected_date" value="<?= htmlspecialchars($selectedDate) ?>">
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>OT Hours</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($activeEmps as $emp): 
                                $log = $logs[$emp['id']] ?? ['status'=>'', 'ot_hours'=>0, 'notes'=>''];
                            ?>
                            <tr>
                                <td class="font-semibold"><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']) ?></td>
                                <td class="td-muted"><?= htmlspecialchars($emp['department']) ?></td>
                                <td>
                                    <!-- Hidden select for form submission -->
                                    <select name="emp[<?= $emp['id'] ?>][status]" class="att-status-select" style="display:none;" onchange="updateRowState(this, '<?= $emp['id'] ?>')">
                                        <option value=""></option>
                                        <option value="P" <?= $log['status']==='P'?'selected':'' ?>>P</option>
                                        <option value="H" <?= $log['status']==='H'?'selected':'' ?>>H</option>
                                        <option value="A" <?= $log['status']==='A'?'selected':'' ?>>A</option>
                                        <option value="OT" <?= $log['status']==='OT'?'selected':'' ?>>OT</option>
                                    </select>
                                    <!-- Pill buttons -->
                                    <div class="flex gap-2">
                                        <button type="button" class="att-btn <?= $log['status']==='P'?'selected-p':'' ?>" onclick="setAttStatus('<?= $emp['id'] ?>', 'P')">P</button>
                                        <button type="button" class="att-btn <?= $log['status']==='H'?'selected-h':'' ?>" onclick="setAttStatus('<?= $emp['id'] ?>', 'H')">H</button>
                                        <button type="button" class="att-btn <?= $log['status']==='A'?'selected-a':'' ?>" onclick="setAttStatus('<?= $emp['id'] ?>', 'A')">A</button>
                                        <button type="button" class="att-btn <?= $log['status']==='OT'?'selected-ot':'' ?>" onclick="setAttStatus('<?= $emp['id'] ?>', 'OT')">OT</button>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="0.5" name="emp[<?= $emp['id'] ?>][ot_hours]" id="ot_<?= $emp['id'] ?>" class="form-control" style="width:80px;padding:4px 8px;" value="<?= $log['ot_hours'] ?: '' ?>" <?= $log['status']==='OT'?'':'disabled' ?>>
                                </td>
                                <td>
                                    <input type="text" name="emp[<?= $emp['id'] ?>][notes]" class="form-control" style="padding:4px 8px;" value="<?= htmlspecialchars($log['notes']) ?>" placeholder="Optional note">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- Monthly Summary Tab -->
    <div data-tab-group="att" data-tab="monthly" style="display:none;">
        <div class="card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Present</th>
                            <th>Half-Day</th>
                            <th>Absent</th>
                            <th>OT Days</th>
                            <th>OT Hours</th>
                            <th>Total Days Worked</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($monthly as $m): 
                            $totalDays = ($m['p_days']*1.0) + ($m['h_days']*0.5) + ($m['ot_days']*1.0);
                        ?>
                        <tr>
                            <td class="font-semibold"><?= htmlspecialchars($m['first_name'].' '.$m['last_name']) ?></td>
                            <td class="td-muted"><?= htmlspecialchars($m['department']) ?></td>
                            <td><span class="badge badge-success"><?= $m['p_days'] ?></span></td>
                            <td><span class="badge badge-warning"><?= $m['h_days'] ?></span></td>
                            <td><span class="badge badge-danger"><?= $m['a_days'] ?></span></td>
                            <td><span class="badge badge-info"><?= $m['ot_days'] ?></span></td>
                            <td class="font-semibold text-main"><?= (float)$m['tot_ot'] ?>h</td>
                            <td class="font-bold text-main"><?= number_format($totalDays, 1) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$monthly): ?>
                        <tr><td colspan="8" class="empty-state">No attendance records for this month.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

  </div>
</div>

<script>
function setAttStatus(empId, status) {
    const sel = document.querySelector(`select[name="emp[${empId}][status]"]`);
    if(sel) {
        sel.value = status;
        updateRowState(sel, empId);
    }
}
function updateRowState(selectEl, empId) {
    const val = selectEl.value;
    const row = selectEl.closest('tr');
    const btns = row.querySelectorAll('.att-btn');
    btns[0].className = 'att-btn ' + (val==='P' ? 'selected-p' : '');
    btns[1].className = 'att-btn ' + (val==='H' ? 'selected-h' : '');
    btns[2].className = 'att-btn ' + (val==='A' ? 'selected-a' : '');
    btns[3].className = 'att-btn ' + (val==='OT'? 'selected-ot': '');
    
    const otInput = document.getElementById('ot_'+empId);
    if(otInput) {
        otInput.disabled = (val !== 'OT');
        if(val !== 'OT') otInput.value = '';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
