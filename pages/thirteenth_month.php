<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = '13th Month Pay';
$currentPage = 'thirteenth_month';
$pdo = getDB();

$error = $_GET['error'] ?? null;
$msg = $_GET['msg'] ?? '';
$year = $_GET['year'] ?? date('Y');

// Fetch records
$stmtRec = $pdo->prepare("SELECT * FROM thirteenth_month WHERE year = ? ORDER BY employee_name");
$stmtRec->execute([$year]);
$records = $stmtRec->fetchAll();

$totComputed = 0; $totApp = 0; $totPaid = 0; $pendingCount = 0;
foreach($records as $r) {
    $totComputed += $r['computed_amount'];
    if ($r['status'] === 'Approved' || $r['status'] === 'Paid') $totApp += $r['computed_amount'];
    if ($r['status'] === 'Paid') $totPaid += $r['computed_amount'];
    if ($r['status'] === 'Pending') $pendingCount++;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header">
      <div>
        <h1>13th Month Pay - <?= htmlspecialchars($year) ?></h1>
        <p>Annual mandatory pro-rated 13th month computation</p>
      </div>
    </div>

    <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><script>document.addEventListener('DOMContentLoaded', ()=>showToast('<?= htmlspecialchars($msg) ?>'));</script><?php endif; ?>

    <div class="stats-grid mb-4">
      <div class="stat-card">
        <div class="stat-label">Total 13th Month Liability</div>
        <div class="stat-value text-main"><?= formatCurrency($totComputed) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Approved Total</div>
        <div class="stat-value text-main" style="color:var(--primary)"><?= formatCurrency($totApp) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Paid Total</div>
        <div class="stat-value" style="color:var(--success)"><?= formatCurrency($totPaid) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Pending Approval</div>
        <div class="stat-value" style="color:var(--warning)"><?= $pendingCount ?> <span style="font-size:13px;color:var(--text-muted);font-weight:normal;">employees</span></div>
      </div>
    </div>
    
    <div style="background:var(--surface-alt);padding:12px 16px;border-radius:var(--radius);margin-bottom:24px;font-size:13px;color:var(--text-main);display:flex;align-items:center;gap:12px;">
        <span style="font-size:20px;">ℹ️</span>
        <div><strong>Formula:</strong> 13th Month Pay = (Monthly Basic Salary × Months Worked) ÷ 12</div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Computation Report</h3></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Monthly Basic</th>
                        <th>Months Worked</th>
                        <th>13th Month Amt</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($records as $r): ?>
                    <tr>
                        <td class="font-semibold text-main"><?= htmlspecialchars($r['employee_name']) ?></td>
                        <td class="td-muted"><?= htmlspecialchars($r['department']) ?></td>
                        <td><?= formatCurrency($r['monthly_basic']) ?></td>
                        <td><?= (float)$r['months_worked'] ?> / 12</td>
                        <td>
                            <div style="color:var(--success);font-weight:700;"><?= formatCurrency($r['computed_amount']) ?></div>
                            <?php if($r['months_worked'] < 12): ?><div style="font-size:11px;color:var(--warning);">Pro-rated</div><?php endif; ?>
                        </td>
                        <td><span class="badge <?= getStatusBadgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                        <td>
                            <?php if($r['status'] === 'Pending'): ?>
                                <form method="POST" action="<?= BASE_URL ?>/api/thirteenth_month.php">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                                    <button class="btn btn-success btn-sm">Approve</button>
                                </form>
                            <?php elseif($r['status'] === 'Approved'): ?>
                                <form method="POST" action="<?= BASE_URL ?>/api/thirteenth_month.php">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                                    <button class="btn btn-primary btn-sm">Mark Paid</button>
                                </form>
                            <?php elseif($r['status'] === 'Paid'): ?>
                                <span class="badge badge-success">Paid <?= formatDate($r['payment_date']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right;">Total:</td>
                        <td colspan="3" style="color:var(--success);font-size:16px;"><?= formatCurrency($totComputed) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
