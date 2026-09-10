<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Payslips Viewer';
$currentPage = 'payslips_viewer';
$pdo = getDB();

$msg = $_GET['msg'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (($_POST['action'] ?? '') === 'delete_payslip') {
        $payslipId = filter_input(INPUT_POST, 'payslip_id', FILTER_VALIDATE_INT);

        if (!$payslipId) {
            http_response_code(400);
            die('Invalid payslip selected.');
        }

        $stmt = $pdo->prepare('DELETE FROM payroll_items WHERE id = ?');
        $stmt->execute([$payslipId]);
        auditLog('Payslip Deleted', "Deleted payroll item ID {$payslipId}");

        $yearParam = urlencode((string) ($_POST['year'] ?? date('Y')));
        header('Location: ' . BASE_URL . "/index.php?page=payslips_viewer&year={$yearParam}&msg=" . urlencode('Payslip deleted successfully.'));
        exit;
    }
}

$year = $_GET['year'] ?? date('Y');
$user = getCurrentUser();

// Fetch payslips for current year
$stmt = $pdo->prepare("
    SELECT p.*, r.period 
    FROM payroll_items p 
    JOIN payroll_runs r ON p.payroll_run_id = r.id 
    WHERE YEAR(r.period_start) = ? AND p.is_included = 1
    ORDER BY r.period_start DESC
");
// In a real app, add AND p.employee_name = ? to restrict to current user.
$stmt->execute([$year]);
$payslips = $stmt->fetchAll();

$totCount = count($payslips);
$totEarned = 0;
foreach($payslips as $p) {
    if ($p['status'] === 'Paid') $totEarned += $p['net_pay'];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header">
      <div>
        <h1>Payslips Viewer</h1>
        <p>View and download official payroll records</p>
      </div>
      <div class="flex gap-2">
          <form method="GET">
              <input type="hidden" name="page" value="payslips_viewer">
              <select name="year" class="form-control" onchange="this.form.submit()">
                  <option value="2026" <?= $year=='2026'?'selected':'' ?>>2026</option>
                  <option value="2025" <?= $year=='2025'?'selected':'' ?>>2025</option>
              </select>
          </form>
          <a href="?page=payslips_viewer" class="btn btn-ghost">Reset</a>
          <button class="btn btn-secondary">Export All (ZIP)</button>
      </div>
    </div>

    <div class="grid-2 mb-4">
      <div class="stat-card">
        <div class="stat-label">Total Payslips (<?= htmlspecialchars($year) ?>)</div>
        <div class="stat-value"><?= $totCount ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Net Earnings YTD</div>
        <div class="stat-value" style="color:var(--success)"><?= formatCurrency($totEarned) ?></div>
      </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Pay Date</th>
                        <th>Gross Pay</th>
                        <th>Deductions</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($payslips as $p): ?>
                    <tr>
                        <td class="font-semibold text-main"><?= htmlspecialchars($p['period']) ?></td>
                        <td><?= formatDate($p['pay_date']) ?: 'Pending' ?></td>
                        <td><?= formatCurrency($p['gross_pay']) ?></td>
                        <td style="color:var(--danger)">-<?= formatCurrency($p['total_deductions']) ?></td>
                        <td class="font-bold text-main" style="color:var(--success)"><?= formatCurrency($p['net_pay']) ?></td>
                        <td><span class="badge <?= getStatusBadgeClass($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick="viewMyPayslip(<?= htmlspecialchars(json_encode($p)) ?>)">View PDF</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this payslip? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="delete_payslip">
                                <input type="hidden" name="payslip_id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="year" value="<?= htmlspecialchars($year) ?>">
                                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger)">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(!$payslips): ?><tr><td colspan="7" class="empty-state">No payslips found for this year.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($msg): ?>
      <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($msg) ?>));</script>
    <?php endif; ?>

  </div>
</div>

<div id="modalPayslip" class="modal-backdrop" style="display:none;">
  <div class="modal-box modal-lg">
    <div class="modal-header no-print">
      <h3>Payslip Viewer</h3>
      <button type="button" class="modal-close" onclick="closeModal('modalPayslip')">&times;</button>
    </div>
    <div class="modal-body" style="background:#f4f4f5;">
        <div class="payslip-doc">
            <div class="ps-header">
                <div class="ps-company"><?= COMPANY_NAME ?></div>
                <div style="font-size:12px;color:#6b7280;margin:4px 0 12px;">OFFICIAL PAYSLIP</div>
            </div>
            <div class="grid-2 mb-4" style="border-bottom:2px solid #111;padding-bottom:12px;">
                <div>
                    <div><strong>Name:</strong> <span id="ps_name"></span></div>
                    <div><strong>Period:</strong> <span id="ps_period"></span></div>
                </div>
                <div style="text-align:right;">
                    <div><strong>Pay Date:</strong> <span id="ps_date"></span></div>
                    <div><strong>Days Worked:</strong> <span id="ps_days"></span></div>
                </div>
            </div>
            
            <div class="grid-2 mb-4">
                <div style="padding-right:16px;">
                    <div class="font-bold mb-2" style="border-bottom:1px solid #e5e7eb;">EARNINGS</div>
                    <div class="ps-row"><span>Basic Pay</span><span id="ps_basic"></span></div>
                    <div class="ps-row"><span>Overtime Pay</span><span id="ps_ot"></span></div>
                    <div class="ps-row"><span>Allowances</span><span id="ps_allow"></span></div>
                    <div class="ps-row"><span>Approved Claims</span><span id="ps_claim"></span></div>
                    <div class="ps-row ps-total" style="margin-top:8px;"><span>Gross Pay</span><span id="ps_gross"></span></div>
                </div>
                <div style="padding-left:16px;border-left:1px solid #e5e7eb;">
                    <div class="font-bold mb-2" style="border-bottom:1px solid #e5e7eb;">DEDUCTIONS</div>
                    <div class="ps-row"><span>SSS Contribution</span><span id="ps_sss"></span></div>
                    <div class="ps-row"><span>PhilHealth</span><span id="ps_ph"></span></div>
                    <div class="ps-row"><span>Pag-IBIG</span><span id="ps_pag"></span></div>
                    <div class="ps-row"><span>Withholding Tax</span><span id="ps_tax"></span></div>
                    <div class="ps-row"><span>Loans / Advances</span><span id="ps_loan"></span></div>
                    <div class="ps-row ps-total" style="margin-top:8px;"><span>Total Deductions</span><span id="ps_ded"></span></div>
                </div>
            </div>
            
            <div style="background:#f3f4f6;padding:16px;border-radius:4px;text-align:center;margin-top:24px;">
                <div style="font-size:12px;color:#6b7280;margin-bottom:4px;">NET PAY</div>
                <div id="ps_net" style="font-size:24px;font-weight:700;color:#16a34a;"></div>
            </div>
        </div>
    </div>
    <div class="modal-footer no-print">
      <button type="button" class="btn btn-ghost" onclick="closeModal('modalPayslip')">Close</button>
      <button type="button" class="btn btn-primary" onclick="printPayslip()">Print / Save PDF</button>
    </div>
  </div>
</div>

<script>
function viewMyPayslip(p) {
    document.getElementById('ps_name').textContent = p.employee_name;
    document.getElementById('ps_period').textContent = p.period;
    document.getElementById('ps_date').textContent = p.pay_date || 'Pending';
    document.getElementById('ps_days').textContent = p.days_worked;
    
    document.getElementById('ps_basic').textContent = formatPHP(p.basic_pay);
    document.getElementById('ps_ot').textContent = formatPHP(p.overtime_pay);
    document.getElementById('ps_allow').textContent = formatPHP(p.allowances);
    document.getElementById('ps_claim').textContent = formatPHP(p.claims_amount);
    document.getElementById('ps_gross').textContent = formatPHP(p.gross_pay);
    
    document.getElementById('ps_sss').textContent = formatPHP(p.sss_ee);
    document.getElementById('ps_ph').textContent = formatPHP(p.philhealth_ee);
    document.getElementById('ps_pag').textContent = formatPHP(p.pagibig_ee);
    document.getElementById('ps_tax').textContent = formatPHP(p.withholding_tax);
    document.getElementById('ps_loan').textContent = formatPHP(p.loans_deduction);
    document.getElementById('ps_ded').textContent = formatPHP(p.total_deductions);
    
    document.getElementById('ps_net').textContent = formatPHP(p.net_pay);
    
    openModal('modalPayslip');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
