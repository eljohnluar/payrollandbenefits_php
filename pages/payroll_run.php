<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/claim_recipient_ai.php';
requireLogin();

$pageTitle = 'Payroll Run';
$currentPage = 'payroll_run';
$pdo = getDB();
ensureClaimRecipientSchema($pdo);

$runId = $_GET['run_id'] ?? null;
$msg = $_GET['msg'] ?? '';

// Fetch available runs for dropdown
$runs = $pdo->query("SELECT id, period, status FROM payroll_runs ORDER BY id DESC")->fetchAll();
if (!$runId && $runs) {
    $runId = $runs[0]['id'];
}

$runDetails = null;
$items = [];
if ($runId) {
    $stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE id = ?");
    $stmt->execute([$runId]);
    $runDetails = $stmt->fetch();
    
    if ($runDetails) {
        $stmtI = $pdo->prepare("SELECT * FROM payroll_items WHERE payroll_run_id = ? ORDER BY department, employee_name");
        $stmtI->execute([$runId]);
        $items = $stmtI->fetchAll();
    }
}

$error = $_GET['error'] ?? null;

// Group by E-Wallet for disbursement
$disbursements = [];
foreach($items as $it) {
    if ($it['is_included']) {
        $p = $it['ewallet_provider'] ?: 'Other';
        if (!isset($disbursements[$p])) $disbursements[$p] = [];
        $disbursements[$p][] = $it;
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header" style="align-items:center;">
      <div>
        <h1>Payroll Run</h1>
        <p>Compute and process salary distributions</p>
      </div>
      <div>
        <select class="form-control" onchange="window.location.href='?page=payroll_run&run_id='+this.value">
            <?php foreach($runs as $r): ?>
                <option value="<?= $r['id'] ?>" <?= $runId == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['period']) ?> (<?= $r['status'] ?>)</option>
            <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if (isset($error)): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><script>document.addEventListener('DOMContentLoaded', ()=>showToast('<?= htmlspecialchars($msg) ?>'));</script><?php endif; ?>

    <?php if ($runDetails): ?>
    <div class="card mb-4">
        <div class="card-body flex justify-between items-center" style="background:var(--surface-alt);">
            <div>
                <h3 style="color:var(--text-main);margin-bottom:4px;"><?= htmlspecialchars($runDetails['period']) ?></h3>
                <div class="text-muted" style="font-size:12px;"><?= formatDate($runDetails['period_start']) ?> – <?= formatDate($runDetails['period_end']) ?></div>
            </div>
            <div class="flex gap-2 items-center">
                <span class="badge <?= getStatusBadgeClass($runDetails['status']) ?>" style="font-size:13px;padding:6px 12px;margin-right:12px;"><?= htmlspecialchars($runDetails['status']) ?></span>
                <?php if ($runDetails['status'] === 'Draft'): ?>
                <form method="POST" action="<?= BASE_URL ?>/api/payroll_run.php" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="recompute">
                    <input type="hidden" name="run_id" value="<?= $runId ?>">
                    <button type="submit" class="btn btn-secondary">Recompute</button>
                </form>
                <form method="POST" action="<?= BASE_URL ?>/api/payroll_run.php" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="run_payroll">
                    <input type="hidden" name="run_id" value="<?= $runId ?>">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Approve this payroll run?')">Approve Run</button>
                </form>
                <?php elseif ($runDetails['status'] === 'Approved'): ?>
                <form method="POST" action="<?= BASE_URL ?>/api/payroll_run.php" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="run_id" value="<?= $runId ?>">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Mark as paid and disburse?')">Mark Paid</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <?php
    $totInc = 0; $totGross = 0; $totDed = 0; $totNet = 0;
    foreach($items as $i) { if($i['is_included']) { $totInc++; $totGross+=$i['gross_pay']; $totDed+=$i['total_deductions']; $totNet+=$i['net_pay']; } }
    ?>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Employees Included</div>
        <div class="stat-value"><?= $totInc ?> <span class="text-muted" style="font-size:14px;">/ <?= count($items) ?></span></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Gross Pay</div>
        <div class="stat-value text-main"><?= formatCurrency($totGross) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Deductions</div>
        <div class="stat-value" style="color:var(--danger)">-<?= formatCurrency($totDed) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Net Pay</div>
        <div class="stat-value" style="color:var(--success)"><?= formatCurrency($totNet) ?></div>
      </div>
    </div>

    <!-- Master Payroll Table -->
    <div class="card mb-4">
        <div class="card-header"><h3>Payroll Master List</h3></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;">Inc</th>
                        <th>Employee / Dept</th>
                        <th>Gross Pay</th>
                        <th>SSS</th>
                        <th>PhilHealth</th>
                        <th>Pag-IBIG</th>
                        <th>W/Tax</th>
                        <th>Loans</th>
                        <th>Net Pay</th>
                        <th>E-Wallet</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $it): ?>
                    <tr>
                        <td>
                            <input type="checkbox" <?= $it['is_included']?'checked':'' ?> <?= $runDetails['status']!=='Draft'?'disabled':'' ?> 
                                   onchange="toggleInclude(<?= $it['id'] ?>, this.checked)">
                        </td>
                        <td>
                            <div class="font-semibold"><?= htmlspecialchars($it['employee_name']) ?></div>
                            <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($it['department']) ?></div>
                        </td>
                        <td class="font-semibold text-main"><?= formatCurrency($it['gross_pay']) ?></td>
                        <td class="td-muted"><?= formatCurrency($it['sss_ee']) ?></td>
                        <td class="td-muted"><?= formatCurrency($it['philhealth_ee']) ?></td>
                        <td class="td-muted"><?= formatCurrency($it['pagibig_ee']) ?></td>
                        <td class="td-muted"><?= formatCurrency($it['withholding_tax']) ?></td>
                        <td class="td-muted" style="color:var(--danger)"><?= formatCurrency($it['loans_deduction']) ?></td>
                        <td class="font-bold text-main"><?= formatCurrency($it['net_pay']) ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($it['ewallet_provider']?:'Other') ?></span></td>
                        <td><span class="badge <?= getStatusBadgeClass($it['status']) ?>"><?= htmlspecialchars($it['status']) ?></span></td>
                        <td>
                            <button class="btn btn-ghost btn-sm" onclick="togglePayrollRow('<?= $it['employee_id'] ?>')">Info</button>
                        </td>
                    </tr>
                    <!-- Expandable Detail Row -->
                    <tr id="pr-detail-<?= $it['employee_id'] ?>" style="display:none;background:var(--surface-alt);">
                        <td colspan="12" style="padding:16px;">
                            <div class="grid-4" style="font-size:12px;">
                                <div>
                                    <div class="font-semibold mb-2">Basic Pay Formula</div>
                                    <div class="text-muted">Days Worked: <?= (float)$it['days_worked'] ?></div>
                                    <div class="text-main font-semibold mt-2"><?= formatCurrency($it['basic_pay']) ?></div>
                                </div>
                                <div>
                                    <div class="font-semibold mb-2">Overtime Pay</div>
                                    <div class="text-muted">OT Hours: <?= (float)$it['ot_hours'] ?></div>
                                    <div class="text-main font-semibold mt-2"><?= formatCurrency($it['overtime_pay']) ?></div>
                                </div>
                                <div>
                                    <div class="font-semibold mb-2">Allowances</div>
                                    <div class="text-main font-semibold mt-2"><?= formatCurrency($it['allowances']) ?></div>
                                </div>
                                <div>
                                    <div class="font-semibold mb-2">Approved Claims</div>
                                    <div class="text-main font-semibold mt-2"><?= formatCurrency($it['claims_amount']) ?></div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="8" style="text-align:right;">TOTAL NET PAY:</td>
                        <td colspan="4" style="color:var(--success);font-size:16px;"><?= formatCurrency($totNet) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Output Generation placeholders -->
    <div class="card mb-4">
        <div class="card-body flex items-center gap-4" style="background:rgba(16, 185, 129, 0.1); border-left:4px solid var(--primary);">
            <div style="font-size:24px;">📄</div>
            <div>
                <h4 style="color:var(--text-main);margin-bottom:4px;">Automated Outputs</h4>
                <p class="text-muted" style="margin:0;font-size:13px;">Payslip PDF generation via Dompdf and Email Notifications to employees will trigger when the run is marked as <strong>Paid</strong>.</p>
            </div>
        </div>
    </div>

    <!-- Disbursement Summary -->
    <h3 style="font-size:16px;color:var(--text-main);margin-bottom:16px;">Disbursement Summary</h3>
    <div class="grid-2">
        <?php foreach($disbursements as $prov => $emlist): 
            $sub = 0; foreach($emlist as $e) $sub += $e['net_pay'];
        ?>
        <div class="card">
            <div class="card-header">
                <h3><?= htmlspecialchars($prov) ?></h3>
                <span class="badge badge-primary"><?= count($emlist) ?> Employees</span>
            </div>
            <div class="card-body" style="max-height:200px;overflow-y:auto;padding:10px 20px;">
                <?php foreach($emlist as $e): ?>
                <div class="flex justify-between mb-2 pb-2" style="border-bottom:1px solid var(--border);font-size:13px;">
                    <span class="text-main"><?= htmlspecialchars($e['employee_name']) ?></span>
                    <span class="font-semibold"><?= formatCurrency($e['net_pay']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer flex justify-between items-center">
                <span class="font-bold text-main">Subtotal: <?= formatCurrency($sub) ?></span>
                <button class="btn btn-ghost btn-sm">Export CSV</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php else: ?>
        <div class="empty-state">No payroll run selected or found.</div>
    <?php endif; ?>

  </div>
</div>

<script>
function toggleInclude(itemId, isIncluded) {
    const fd = new FormData();
    fd.append('csrf_token', '<?= csrfToken() ?>');
    fd.append('action', 'toggle_include');
    fd.append('run_id', '<?= $runId ?>');
    fd.append('item_id', itemId);
    fd.append('is_included', isIncluded ? 1 : 0);
    
    fetch('<?= BASE_URL ?>/api/payroll_run.php', { method: 'POST', body: fd }).then(() => {
        showToast('Inclusion status updated. Recompute required.', 'warning');
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
