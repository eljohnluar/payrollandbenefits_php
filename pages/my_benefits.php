<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Benefits Enrollment';
$currentPage = 'my_benefits';
$pdo = getDB();

$empFilter = $_GET['emp_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    if (isset($_POST['action']) && $_POST['action'] === 'enroll') {
        try {
            $empId = $_POST['employee_id'] ?? '';
            $planId = $_POST['plan_id'] ?? '';
            $effDate = $_POST['effective_date'] ?? date('Y-m-d');
            $depCount = (int)($_POST['dependents'] ?? 0);
            
            $empStmt = $pdo->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
            $empStmt->execute([$empId]);
            $empRow = $empStmt->fetch();
            $empName = $empRow ? ($empRow['first_name'] . ' ' . $empRow['last_name']) : 'Unknown Employee';

            $plStmt = $pdo->prepare("SELECT plan_name, provider, monthly_premium, employer_share, employee_share FROM benefit_plans WHERE id = ?");
            $plStmt->execute([$planId]);
            $plRow = $plStmt->fetch();

            $erShare = $plRow['employer_share'] ?? 0;
            $eeShare = $plRow['employee_share'] ?? (100 - $erShare);

            $stmt = $pdo->prepare("INSERT INTO benefit_enrollments (employee_id, employee_name, plan_id, plan_name, provider, monthly_premium, employer_share, employee_share, dependents, effective_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
            $stmt->execute([
                $empId,
                $empName,
                $planId,
                $plRow['plan_name'] ?? '',
                $plRow['provider'] ?? '',
                $plRow['monthly_premium'] ?? 0,
                $erShare,
                $eeShare,
                $depCount,
                $effDate
            ]);
            auditLog('Benefit Enrolled', "Enrolled employee {$empName} in plan " . ($plRow['plan_name'] ?? $planId));
            header('Location: ' . BASE_URL . '/index.php?page=my_benefits&msg=' . urlencode('Enrollment successful.'));
            exit;
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'cancel_enrollment') {
        $enrId = $_POST['enrollment_id'] ?? '';
        $pdo->prepare("UPDATE benefit_enrollments SET status='Cancelled' WHERE id=?")->execute([$enrId]);
        auditLog('Benefit Enrollment Cancelled', "Cancelled enrollment ID {$enrId}");
        header('Location: ' . BASE_URL . '/index.php?page=my_benefits&msg=' . urlencode('Enrollment cancelled.'));
        exit;
    }
}

$msg = $_GET['msg'] ?? '';

// Stats
$tot = $pdo->query("SELECT COUNT(*) FROM benefit_enrollments")->fetchColumn();
$act = $pdo->query("SELECT COUNT(*) FROM benefit_enrollments WHERE status='Active'")->fetchColumn();
$deps = $pdo->query("SELECT SUM(dependents) FROM benefit_enrollments WHERE status='Active'")->fetchColumn() ?: 0;
$prem = $pdo->query("
    SELECT SUM(p.monthly_premium) 
    FROM benefit_enrollments e 
    JOIN benefit_plans p ON e.plan_id = p.id 
    WHERE e.status='Active'
")->fetchColumn() ?: 0;

// Fetch enrollments with optional employee filter
$query = "
    SELECT e.*, emp.first_name, emp.last_name, emp.department, emp.code, p.plan_name, p.provider, p.monthly_premium, p.employer_share 
    FROM benefit_enrollments e
    JOIN employees emp ON e.employee_id = emp.id
    JOIN benefit_plans p ON e.plan_id = p.id
    WHERE 1=1
";
$params = [];
if ($empFilter) {
    $query .= " AND e.employee_id = ?";
    $params[] = $empFilter;
}
$query .= " ORDER BY e.id DESC";
$stmtEnr = $pdo->prepare($query);
$stmtEnr->execute($params);
$enrollments = $stmtEnr->fetchAll();

// For modal and filter
$emps = $pdo->query("SELECT id, first_name, last_name, code FROM employees WHERE status IN ('Active','On Leave') ORDER BY first_name")->fetchAll();
$plans = $pdo->query("SELECT * FROM benefit_plans ORDER BY plan_name")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header">
      <div>
        <h1>Benefits Enrollment</h1>
        <p>Manage employee health, insurance, and retirement plans</p>
      </div>
      <button class="btn btn-primary" onclick="openModal('modalEnroll')">Enroll Employee</button>
    </div>

    <?php if (isset($error)): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><script>document.addEventListener('DOMContentLoaded', ()=>showToast('<?= htmlspecialchars($msg) ?>'));</script><?php endif; ?>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Enrollments</div>
        <div class="stat-value"><?= $tot ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Active Plans</div>
        <div class="stat-value text-main" style="color:var(--success)"><?= $act ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Dependents</div>
        <div class="stat-value text-main"><?= $deps ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Monthly Premium</div>
        <div class="stat-value" style="color:var(--primary)"><?= formatCurrency($prem) ?></div>
      </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Enrollment Records</h3></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Plan & Provider</th>
                        <th>Monthly Premium</th>
                        <th>EE Share</th>
                        <th>ER Share</th>
                        <th>Dependents</th>
                        <th>Effective Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($enrollments as $e): 
                        $erAmt = $e['monthly_premium'] * ($e['employer_share']/100);
                        $eeAmt = $e['monthly_premium'] - $erAmt;
                        $eePct = 100 - $e['employer_share'];
                    ?>
                    <tr>
                        <td>
                            <div class="font-semibold text-main"><?= htmlspecialchars($e['first_name'].' '.$e['last_name']) ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($e['department']) ?></div>
                        </td>
                        <td>
                            <div class="font-semibold"><?= htmlspecialchars($e['plan_name']) ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($e['provider']) ?></div>
                        </td>
                        <td class="font-semibold text-main"><?= formatCurrency($e['monthly_premium']) ?></td>
                        <td>
                            <div style="color:var(--danger);font-weight:600;"><?= formatCurrency($eeAmt) ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= $eePct ?>%</div>
                        </td>
                        <td>
                            <div style="color:var(--success);font-weight:600;"><?= formatCurrency($erAmt) ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= $e['employer_share'] ?>%</div>
                        </td>
                        <td class="font-semibold text-center"><?= $e['dependents'] ?></td>
                        <td><?= formatDate($e['effective_date']) ?></td>
                        <td><span class="badge <?= getStatusBadgeClass($e['status']) ?>"><?= htmlspecialchars($e['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

  </div>
</div>

<div id="modalEnroll" class="modal-backdrop" style="display:none;">
  <div class="modal-box">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="enroll">
      
      <div class="modal-header">
        <h3>Enroll Employee to Benefit Plan</h3>
        <button type="button" class="modal-close" onclick="closeModal('modalEnroll')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
            <label class="form-label required">Employee</label>
            <select name="employee_id" class="form-control" required>
                <option value="">Select Employee</option>
                <?php foreach($emps as $e): ?>
                    <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['first_name'].' '.$e['last_name'].' ('.$e['code'].')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label required">Benefit Plan</label>
            <select name="plan_id" class="form-control" required onchange="updateShares(this)">
                <option value="" data-ee="0" data-er="0">Select Plan</option>
                <?php foreach($plans as $p): ?>
                    <option value="<?= $p['id'] ?>" data-ee="<?= 100 - $p['employer_share'] ?>" data-er="<?= $p['employer_share'] ?>">
                        <?= htmlspecialchars($p['plan_name'] . ' - ' . formatCurrency($p['monthly_premium'])) ?>/mo
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="grid-2 mt-2" style="background:var(--surface-alt);padding:10px;border-radius:4px;font-size:12px;">
                <div><span class="text-muted">Employee Share: </span><strong id="ee-share" style="color:var(--danger)">0%</strong></div>
                <div><span class="text-muted">Company Share: </span><strong id="er-share" style="color:var(--success)">0%</strong></div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label required">Effective Date</label>
                <input type="date" name="effective_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label required">Dependents Enrolled</label>
                <input type="number" name="dependents" class="form-control" min="0" value="0" required>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalEnroll')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Enrollment</button>
      </div>
    </form>
  </div>
</div>

<script>
function updateShares(select) {
    const opt = select.options[select.selectedIndex];
    document.getElementById('ee-share').textContent = (opt.dataset.ee || '0') + '%';
    document.getElementById('er-share').textContent = (opt.dataset.er || '0') + '%';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
