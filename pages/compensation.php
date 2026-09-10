<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Compensation';
$currentPage = 'compensation';
$pdo = getDB();

$empId = $_GET['emp_id'] ?? '';
$error = '';
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $empId) {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_salary') {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE employees SET basic_salary = ? WHERE id = ?");
            $stmt->execute([$_POST['basic_salary'], $empId]);
            
            $stmtHist = $pdo->prepare("INSERT INTO salary_history (employee_id, basic_salary, effective_date, reason) VALUES (?, ?, ?, ?)");
            $stmtHist->execute([$empId, $_POST['basic_salary'], $_POST['effective_date'], $_POST['reason']]);
            
            auditLog('Salary Updated', "Updated salary for emp {$empId}");
            $pdo->commit();
            $success = 'Salary updated successfully.';
        } elseif ($action === 'add_allowance') {
            $stmt = $pdo->prepare("INSERT INTO allowances (employee_id, type, amount, frequency, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$empId, $_POST['type'], $_POST['amount'], $_POST['frequency'], isset($_POST['is_active']) ? 1 : 0]);
            $success = 'Allowance added.';
        } elseif ($action === 'edit_allowance') {
            $stmt = $pdo->prepare("UPDATE allowances SET type=?, amount=?, frequency=?, is_active=? WHERE id=? AND employee_id=?");
            $stmt->execute([$_POST['type'], $_POST['amount'], $_POST['frequency'], isset($_POST['is_active']) ? 1 : 0, $_POST['allowance_id'], $empId]);
            $success = 'Allowance updated.';
        } elseif ($action === 'delete_allowance') {
            $stmt = $pdo->prepare("DELETE FROM allowances WHERE id=? AND employee_id=?");
            $stmt->execute([$_POST['allowance_id'], $empId]);
            $success = 'Allowance deleted.';
        } elseif ($action === 'add_loan') {
            $stmt = $pdo->prepare("INSERT INTO loans (employee_id, type, total_amount, monthly_deduction, remaining_balance, start_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$empId, $_POST['type'], $_POST['total_amount'], $_POST['monthly_deduction'], $_POST['total_amount'], $_POST['start_date']]);
            $success = 'Loan added.';
        } elseif ($action === 'delete_loan') {
            $stmt = $pdo->prepare("DELETE FROM loans WHERE id=? AND employee_id=?");
            $stmt->execute([$_POST['loan_id'], $empId]);
            $success = 'Loan deleted.';
        }
        
        // Redirect to avoid re-post on refresh
        header('Location: ' . BASE_URL . "/index.php?page=compensation&emp_id={$empId}&msg=" . urlencode($success));
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

$msg = $_GET['msg'] ?? '';

// Fetch Employees for dropdown
$allEmployees = $pdo->query("SELECT id, first_name, last_name, code FROM employees ORDER BY first_name")->fetchAll();

$employee = null;
$history = [];
$allowances = [];
$loans = [];
$activeAllowancesTotal = 0;

if ($empId) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$empId]);
    $employee = $stmt->fetch();
    
    if ($employee) {
        $stmtH = $pdo->prepare("SELECT * FROM salary_history WHERE employee_id = ? ORDER BY effective_date DESC, id DESC");
        $stmtH->execute([$empId]);
        $history = $stmtH->fetchAll();
        
        $stmtA = $pdo->prepare("SELECT * FROM allowances WHERE employee_id = ? ORDER BY id ASC");
        $stmtA->execute([$empId]);
        $allowances = $stmtA->fetchAll();
        
        foreach($allowances as $a) {
            if ($a['is_active'] && $a['frequency'] === 'Monthly') {
                $activeAllowancesTotal += $a['amount'];
            }
        }
        
        $stmtL = $pdo->prepare("SELECT * FROM loans WHERE employee_id = ? ORDER BY id ASC");
        $stmtL->execute([$empId]);
        $loans = $stmtL->fetchAll();
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    <div class="page-header">
      <div>
        <h1>Compensation</h1>
        <p>Manage salary, allowances, and deductions</p>
      </div>
      <div>
        <select class="form-control" onchange="window.location.href='?page=compensation&emp_id='+this.value" style="width:250px;">
          <option value="">-- Select Employee --</option>
          <?php foreach($allEmployees as $e): ?>
            <option value="<?= $e['id'] ?>" <?= $empId === $e['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name'] . ' (' . $e['code'] . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><script>document.addEventListener('DOMContentLoaded', ()=>showToast('<?= htmlspecialchars($msg) ?>'));</script><?php endif; ?>

    <?php if ($employee): ?>
    <!-- Employee Banner -->
    <div class="card mb-4" style="background:var(--primary-light);border-color:var(--primary);">
        <div class="card-body flex items-center gap-4">
            <div class="topbar-avatar" style="width:60px;height:60px;font-size:24px;background:var(--primary);color:#fff;">
                <?= strtoupper(substr($employee['first_name'],0,1) . substr($employee['last_name'],0,1)) ?>
            </div>
            <div style="flex:1;">
                <h2 style="font-size:20px;font-weight:700;color:var(--text-main);margin-bottom:4px;"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h2>
                <div class="text-muted" style="font-size:13px;">
                    <?= htmlspecialchars($employee['position']) ?> • <?= htmlspecialchars($employee['department']) ?> • <?= htmlspecialchars($employee['code']) ?>
                </div>
            </div>
            <div><span class="badge <?= getStatusBadgeClass($employee['status']) ?>"><?= htmlspecialchars($employee['status']) ?></span></div>
        </div>
    </div>

    <div class="grid-2">
      <!-- LEFT COLUMN -->
      <div>
        <!-- Salary Structure -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Salary Structure</h3>
                <button class="btn btn-ghost btn-sm" onclick="document.getElementById('editSalForm').style.display='block'">Edit</button>
            </div>
            <div class="card-body">
                <div class="grid-2 mb-4">
                    <div>
                        <div class="stat-label">Monthly Basic Salary</div>
                        <div class="stat-value"><?= formatCurrency($employee['basic_salary']) ?></div>
                    </div>
                    <div>
                        <div class="stat-label">Active Monthly Allowances</div>
                        <div class="stat-value text-main" style="font-size:20px;"><?= formatCurrency($activeAllowancesTotal) ?></div>
                    </div>
                    <div>
                        <div class="stat-label">Daily Rate (Est.)</div>
                        <div class="font-semibold text-main"><?= formatCurrency($employee['basic_salary']/22) ?></div>
                    </div>
                    <div>
                        <div class="stat-label">Hourly Rate (Est.)</div>
                        <div class="font-semibold text-main"><?= formatCurrency($employee['basic_salary']/22/8) ?></div>
                    </div>
                </div>
                
                <!-- Edit Salary Form (Hidden by default) -->
                <form id="editSalForm" method="POST" style="display:none;background:var(--surface-alt);padding:16px;border-radius:var(--radius);margin-top:16px;">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="update_salary">
                    <h4 class="mb-4" style="color:var(--text-main);font-size:13px;">Update Basic Salary</h4>
                    <div class="form-row mb-4">
                        <div class="form-group mb-0"><label class="form-label required">New Basic Salary</label><input type="number" step="0.01" name="basic_salary" class="form-control" value="<?= $employee['basic_salary'] ?>" required></div>
                        <div class="form-group mb-0"><label class="form-label required">Effective Date</label><input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label required">Reason</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Annual merit increase" required>
                    </div>
                    <div class="flex gap-2 justify-between">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('editSalForm').style.display='none'">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Salary History -->
        <div class="card mb-4">
            <div class="card-header"><h3>Salary History</h3></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Effective Date</th><th>Basic Salary</th><th>Reason</th></tr></thead>
                    <tbody>
                        <?php foreach($history as $h): ?>
                        <tr>
                            <td><?= formatDate($h['effective_date']) ?></td>
                            <td class="font-semibold"><?= formatCurrency($h['basic_salary']) ?></td>
                            <td class="td-muted"><?= htmlspecialchars($h['reason'] ?: '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$history): ?><tr><td colspan="3" class="empty-state">No history records.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
      </div>

      <!-- RIGHT COLUMN -->
      <div>
        <!-- Allowances -->
        <div class="card mb-4">
            <div class="card-header"><h3>Allowances</h3></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Type</th><th>Amount</th><th>Frequency</th><th>Active</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($allowances as $a): ?>
                        <tr>
                            <td class="font-semibold"><?= htmlspecialchars($a['type']) ?></td>
                            <td><?= formatCurrency($a['amount']) ?></td>
                            <td><span class="badge badge-muted"><?= htmlspecialchars($a['frequency']) ?></span></td>
                            <td><?= $a['is_active'] ? '<span style="color:var(--success)">Yes</span>' : '<span class="td-muted">No</span>' ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this allowance?');">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete_allowance">
                                    <input type="hidden" name="allowance_id" value="<?= $a['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">Del</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button class="btn btn-secondary btn-sm mb-4" onclick="document.getElementById('addAllowForm').style.display='block'">+ Add Allowance</button>
                <form id="addAllowForm" method="POST" style="display:none;background:var(--bg);padding:16px;border-radius:var(--radius);border:1px solid var(--border);">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="add_allowance">
                    <div class="form-row mb-4">
                        <div class="form-group mb-0">
                            <label class="form-label required">Type</label>
                            <select name="type" class="form-control" required>
                                <option value="Rice">Rice</option>
                                <option value="Transport">Transport</option>
                                <option value="Meal">Meal</option>
                                <option value="Communication">Communication</option>
                                <option value="Clothing">Clothing</option>
                                <option value="Housing">Housing</option>
                            </select>
                        </div>
                        <div class="form-group mb-0"><label class="form-label required">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
                    </div>
                    <div class="form-row mb-4">
                        <div class="form-group mb-0">
                            <label class="form-label required">Frequency</label>
                            <select name="frequency" class="form-control" required>
                                <option value="Monthly">Monthly</option>
                                <option value="Quarterly">Quarterly</option>
                                <option value="Annual">Annual</option>
                                <option value="One-Time">One-Time</option>
                            </select>
                        </div>
                        <div class="form-group mb-0 flex items-center" style="padding-top:24px;">
                            <label style="cursor:pointer;display:flex;align-items:center;gap:8px;color:var(--text-main);font-size:13px;">
                                <input type="checkbox" name="is_active" checked> Active
                            </label>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('addAllowForm').style.display='none'">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Loans & Deductions -->
        <div class="card">
            <div class="card-header"><h3>Loans & Deductions</h3></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Type</th><th>Total Amt</th><th>Monthly</th><th>Balance</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($loans as $l): ?>
                        <tr>
                            <td class="font-semibold"><?= htmlspecialchars($l['type']) ?></td>
                            <td><?= formatCurrency($l['total_amount']) ?></td>
                            <td style="color:var(--danger)">-<?= formatCurrency($l['monthly_deduction']) ?></td>
                            <td class="font-semibold"><?= formatCurrency($l['remaining_balance']) ?></td>
                            <td>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this loan?');">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete_loan">
                                    <input type="hidden" name="loan_id" value="<?= $l['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">Del</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <button class="btn btn-secondary btn-sm mb-4" onclick="document.getElementById('addLoanForm').style.display='block'">+ Add Loan</button>
                <form id="addLoanForm" method="POST" style="display:none;background:var(--bg);padding:16px;border-radius:var(--radius);border:1px solid var(--border);">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="add_loan">
                    <div class="form-row mb-4">
                        <div class="form-group mb-0">
                            <label class="form-label required">Type</label>
                            <select name="type" class="form-control" required>
                                <option value="SSS Loan">SSS Loan</option>
                                <option value="Pag-IBIG Loan">Pag-IBIG Loan</option>
                                <option value="Salary Advance">Salary Advance</option>
                                <option value="Car Loan">Car Loan</option>
                                <option value="Housing Loan">Housing Loan</option>
                            </select>
                        </div>
                        <div class="form-group mb-0"><label class="form-label required">Total Amount</label><input type="number" step="0.01" name="total_amount" class="form-control" required></div>
                    </div>
                    <div class="form-row mb-4">
                        <div class="form-group mb-0"><label class="form-label required">Monthly Deduction</label><input type="number" step="0.01" name="monthly_deduction" class="form-control" required></div>
                        <div class="form-group mb-0"><label class="form-label required">Start Date</label><input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Save</button>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('addLoanForm').style.display='none'">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
      </div>
    </div>
    
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">👥</div>
            <p>Select an employee from the top right dropdown to manage compensation.</p>
        </div>
    <?php endif; ?>

  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
