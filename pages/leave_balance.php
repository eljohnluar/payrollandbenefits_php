<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Leave Balance';
$currentPage = 'leave_balance';
$pdo = getDB();

// Fetch default global balances (where employee_id IS NULL). In a real app this would be specific to logged in user or selected employee.
$stmt = $pdo->prepare("SELECT * FROM leave_balances WHERE employee_id IS NULL");
$stmt->execute();
$balances = $stmt->fetchAll();

$totAccrued = 0; $totUsed = 0; $totAvail = 0;
$cards = [];
foreach($balances as $b) {
    $avail = $b['accrued'] - $b['used'];
    $totAccrued += $b['accrued'];
    $totUsed += $b['used'];
    $totAvail += $avail;
    $cards[$b['leave_type']] = $avail;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header">
      <div>
        <h1>Leave Balance</h1>
        <p>Monitor accrued, used, and available leave credits</p>
      </div>
      <a href="?page=leave_conversion" class="btn btn-primary">Manage Conversions</a>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid" style="margin-bottom:32px;">
      <div class="stat-card" style="background:var(--primary-light);border-color:var(--primary);">
        <div class="stat-label" style="color:var(--primary-dark)">Total Available Days</div>
        <div class="stat-value" style="color:var(--primary);font-size:32px;"><?= $totAvail ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Vacation Leave (VL)</div>
        <div class="stat-value text-main"><?= $cards['Vacation'] ?? 0 ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Sick Leave (SL)</div>
        <div class="stat-value text-main"><?= $cards['Sick'] ?? 0 ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Emergency/Special</div>
        <div class="stat-value text-main"><?= ($cards['Emergency'] ?? 0) + ($cards['Special'] ?? 0) ?></div>
      </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>Leave Breakdown</h3></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Total Accrued (YTD)</th>
                        <th>Used Days</th>
                        <th>Available Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($balances as $b): 
                        $avail = $b['accrued'] - $b['used'];
                    ?>
                    <tr>
                        <td class="font-semibold text-main"><?= htmlspecialchars($b['leave_type']) ?> Leave</td>
                        <td><?= $b['accrued'] ?> days</td>
                        <td style="color:var(--warning)"><?= $b['used'] ?> days</td>
                        <td class="font-bold text-main" style="color:var(--success)"><?= $avail ?> days</td>
                        <td>
                            <?php if($avail > 0): ?>
                                <span class="badge badge-success">Available</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Depleted</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
