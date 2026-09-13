<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Payslips';
$currentPage = 'payslips';
$pdo = getDB();

$empFilter = $_GET['emp_id'] ?? '';
$periodFilter = $_GET['period'] ?? '';
$viewId = $_GET['viewing_id'] ?? '';


// Fetch filters
$employees = $pdo->query("SELECT DISTINCT employee_id, employee_name FROM payroll_items ORDER BY employee_name")->fetchAll();
$periods = $pdo->query("SELECT DISTINCT period FROM payroll_runs ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);

// Build query
$query = "SELECT p.*, r.period FROM payroll_items p JOIN payroll_runs r ON p.payroll_run_id = r.id WHERE p.is_included = 1";
$params = [];

if ($empFilter) {
    $query .= " AND p.employee_id = ?";
    $params[] = $empFilter;
}
if ($periodFilter) {
    $query .= " AND r.period = ?";
    $params[] = $periodFilter;
}
$query .= " ORDER BY r.id DESC, p.employee_name ASC LIMIT 50";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Fetch single for preview
$preview = null;
if ($viewId) {
    $stmtP = $pdo->prepare("SELECT p.*, r.period, r.run_date, e.code, e.department, e.position 
                           FROM payroll_items p 
                           JOIN payroll_runs r ON p.payroll_run_id = r.id 
                           JOIN employees e ON p.employee_id = e.id
                           WHERE p.id = ?");
    $stmtP->execute([$viewId]);
    $preview = $stmtP->fetch();
}

$autoPrint = isset($_GET['print']) && $_GET['print'] == '1';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header no-print">
      <div>
        <h1>Payslips</h1>
        <p>View and print employee payslips</p>
      </div>
      <form class="filters-bar" method="GET" style="margin:0;">
        <input type="hidden" name="page" value="payslips">
        <select name="period" class="form-control" onchange="this.form.submit()">
            <option value="">All Periods</option>
            <?php foreach($periods as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>" <?= $periodFilter === $p ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="emp_id" class="form-control" onchange="this.form.submit()">
            <option value="">All Employees</option>
            <?php foreach($employees as $e): ?>
                <option value="<?= $e['employee_id'] ?>" <?= $empFilter == $e['employee_id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['employee_name']) ?></option>
            <?php endforeach; ?>
        </select>
      </form>
    </div>

    <div class="grid-2">
      <!-- Records List -->
      <div class="card no-print">
        <div class="card-header">
            <h3>Payslip Records</h3>
        </div>
        <div class="table-wrap" style="max-height:600px;overflow-y:auto;">
            <table>
                <thead><tr><th>Employee</th><th>Period</th><th>Net Pay</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    <?php foreach($records as $r): ?>
                    <tr style="<?= $viewId == $r['id'] ? 'background:var(--surface-alt);' : '' ?>">
                        <td class="font-semibold"><?= htmlspecialchars($r['employee_name']) ?></td>
                        <td><?= htmlspecialchars($r['period']) ?></td>
                        <td class="font-bold text-main"><?= formatCurrency($r['net_pay']) ?></td>
                        <td><span class="badge <?= getStatusBadgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                        <td>
                            <a href="?page=payslips&viewing_id=<?= $r['id'] ?>&emp_id=<?= urlencode($empFilter) ?>&period=<?= urlencode($periodFilter) ?>" class="btn btn-ghost btn-sm">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(!$records): ?><tr><td colspan="5" class="empty-state">No records found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
      </div>

      <!-- Preview Panel -->
      <div class="card">
        <div class="card-header no-print">
            <h3>Payslip Preview</h3>
        </div>
        <div class="card-body" style="background:#f4f4f5;">
            <?php if ($preview): ?>
                <div class="payslip-doc">
                    <div class="ps-header">
                        <div class="ps-company"><?= COMPANY_NAME ?></div>
                        <div style="font-size:12px;color:#6b7280;margin:4px 0 12px;">OFFICIAL PAYSLIP</div>
                    </div>
                    <div class="grid-2 mb-4" style="border-bottom:2px solid #111;padding-bottom:12px;">
                        <div>
                            <div><strong>Name:</strong> <?= htmlspecialchars($preview['employee_name']) ?></div>
                            <div><strong>ID:</strong> <?= htmlspecialchars($preview['code']) ?></div>
                            <div><strong>Dept:</strong> <?= htmlspecialchars($preview['department']) ?></div>
                            <div><strong>Position:</strong> <?= htmlspecialchars($preview['position']) ?></div>
                        </div>
                        <div style="text-align:right;">
                            <div><strong>Period:</strong> <?= htmlspecialchars($preview['period']) ?></div>
                            <div><strong>Pay Date:</strong> <?= formatDate($preview['pay_date']) ?: 'Pending' ?></div>
                            <div><strong>Days Worked:</strong> <?= (float)$preview['days_worked'] ?></div>
                            <div><strong>Status:</strong> <?= htmlspecialchars($preview['status']) ?></div>
                        </div>
                    </div>
                    
                    <div class="grid-2 mb-4">
                        <!-- Earnings -->
                        <div style="padding-right:16px;">
                            <div class="font-bold mb-2" style="border-bottom:1px solid #e5e7eb;">EARNINGS</div>
                            <div class="ps-row"><span>Basic Pay</span><span><?= formatCurrency($preview['basic_pay']) ?></span></div>
                            <div class="ps-row"><span>Overtime Pay (<?= (float)$preview['ot_hours'] ?> hrs)</span><span><?= formatCurrency($preview['overtime_pay']) ?></span></div>
                            <div class="ps-row"><span>Allowances</span><span><?= formatCurrency($preview['allowances']) ?></span></div>
                            <div class="ps-row"><span>Approved Claims</span><span><?= formatCurrency($preview['claims_amount']) ?></span></div>
                            <div class="ps-row ps-total" style="margin-top:8px;"><span>Gross Pay</span><span><?= formatCurrency($preview['gross_pay']) ?></span></div>
                        </div>
                        <!-- Deductions -->
                        <div style="padding-left:16px;border-left:1px solid #e5e7eb;">
                            <div class="font-bold mb-2" style="border-bottom:1px solid #e5e7eb;">DEDUCTIONS</div>
                            <div class="ps-row"><span>SSS Contribution</span><span><?= formatCurrency($preview['sss_ee']) ?></span></div>
                            <div class="ps-row"><span>PhilHealth</span><span><?= formatCurrency($preview['philhealth_ee']) ?></span></div>
                            <div class="ps-row"><span>Pag-IBIG</span><span><?= formatCurrency($preview['pagibig_ee']) ?></span></div>
                            <div class="ps-row"><span>Withholding Tax</span><span><?= formatCurrency($preview['withholding_tax']) ?></span></div>
                            <div class="ps-row"><span>Loans / Advances</span><span><?= formatCurrency($preview['loans_deduction']) ?></span></div>
                            <div class="ps-row ps-total" style="margin-top:8px;"><span>Total Deductions</span><span><?= formatCurrency($preview['total_deductions']) ?></span></div>
                        </div>
                    </div>
                    
                    <div style="background:#f3f4f6;padding:16px;border-radius:4px;text-align:center;margin-top:24px;">
                        <div style="font-size:12px;color:#6b7280;margin-bottom:4px;">NET PAY</div>
                        <div style="font-size:24px;font-weight:700;color:#16a34a;"><?= formatCurrency($preview['net_pay']) ?></div>
                    </div>
                </div>
                
                <div class="flex justify-end mt-4 no-print gap-2">
                    <button type="button" class="btn btn-primary" onclick="printPayslip()">Print / Save PDF</button>
                </div>
                <?php if ($autoPrint): ?>
                <script>document.addEventListener('DOMContentLoaded', ()=>window.print());</script>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">Select a payslip record to preview.</div>
            <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
