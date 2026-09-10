<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Tax & Contributions';
$currentPage = 'tax';
$pdo = getDB();

$selectedMonth = $_GET['selected_month'] ?? date('Y-m');

// Fetch active employees with their basic salary and active monthly allowances
$stmt = $pdo->prepare("
    SELECT e.id, e.first_name, e.last_name, e.code, e.basic_salary,
           COALESCE((SELECT SUM(amount) FROM allowances WHERE employee_id=e.id AND is_active=1 AND frequency='Monthly'), 0) as monthly_allowances
    FROM employees e
    WHERE e.status IN ('Active', 'On Leave')
    ORDER BY e.first_name
");
$stmt->execute();
$employees = $stmt->fetchAll();

$totSssEE = 0; $totSssER = 0;
$totPhEE = 0;  $totPhER = 0;
$totPagibigEE = 0; $totPagibigER = 0;
$totTax = 0;

$records = [];
foreach ($employees as $emp) {
    $gross = $emp['basic_salary'] + $emp['monthly_allowances'];
    
    $sss = computeSSS($emp['basic_salary']);
    $ph = computePhilHealth($emp['basic_salary']);
    $pag = computePagIBIG($emp['basic_salary']);
    
    $totalEE = $sss['ee'] + $ph['ee'] + $pag['ee'];
    $tax = computeWithholdingTax($gross, $totalEE);
    
    $totSssEE += $sss['ee']; $totSssER += $sss['er'];
    $totPhEE += $ph['ee']; $totPhER += $ph['er'];
    $totPagibigEE += $pag['ee']; $totPagibigER += $pag['er'];
    $totTax += $tax;
    
    $records[] = [
        'name' => $emp['first_name'] . ' ' . $emp['last_name'],
        'basic' => $emp['basic_salary'],
        'sss_ee' => $sss['ee'], 'sss_er' => $sss['er'],
        'ph_ee' => $ph['ee'], 'ph_er' => $ph['er'],
        'pag_ee' => $pag['ee'], 'pag_er' => $pag['er'],
        'taxable' => $gross - $totalEE,
        'tax' => $tax
    ];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header" style="align-items:center;">
      <div>
        <h1>Tax & Contributions</h1>
        <p>Statutory deductions breakdown based on 2026 rates</p>
      </div>
      <form method="GET">
        <input type="hidden" name="page" value="tax">
        <input type="month" name="selected_month" class="form-control" value="<?= htmlspecialchars($selectedMonth) ?>" onchange="this.form.submit()">
      </form>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total SSS (EE Share)</div>
        <div class="stat-value text-main"><?= formatCurrency($totSssEE) ?></div>
        <div class="stat-change text-muted">ER: <?= formatCurrency($totSssER) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total PhilHealth (EE Share)</div>
        <div class="stat-value text-main"><?= formatCurrency($totPhEE) ?></div>
        <div class="stat-change text-muted">ER: <?= formatCurrency($totPhER) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Pag-IBIG (EE Share)</div>
        <div class="stat-value text-main"><?= formatCurrency($totPagibigEE) ?></div>
        <div class="stat-change text-muted">ER: <?= formatCurrency($totPagibigER) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Withholding Tax</div>
        <div class="stat-value" style="color:var(--danger)"><?= formatCurrency($totTax) ?></div>
        <div class="stat-change text-muted">BIR TRAIN 2026</div>
      </div>
    </div>

    <!-- Details Table -->
    <div class="card mb-4">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Basic Salary</th>
                        <th>SSS EE</th>
                        <th>SSS ER</th>
                        <th>PhilHealth EE</th>
                        <th>PhilHealth ER</th>
                        <th>Pag-IBIG EE</th>
                        <th>Pag-IBIG ER</th>
                        <th>Taxable Income</th>
                        <th>W/Tax</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($records as $r): ?>
                    <tr>
                        <td class="font-semibold"><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= formatCurrency($r['basic']) ?></td>
                        <td class="td-muted"><?= formatCurrency($r['sss_ee']) ?></td>
                        <td class="td-muted"><?= formatCurrency($r['sss_er']) ?></td>
                        <td class="td-muted"><?= formatCurrency($r['ph_ee']) ?></td>
                        <td class="td-muted"><?= formatCurrency($r['ph_er']) ?></td>
                        <td class="td-muted"><?= formatCurrency($r['pag_ee']) ?></td>
                        <td class="td-muted"><?= formatCurrency($r['pag_er']) ?></td>
                        <td class="font-semibold"><?= formatCurrency($r['taxable']) ?></td>
                        <td style="color:var(--danger);font-weight:600;"><?= formatCurrency($r['tax']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(!$records): ?>
                    <tr><td colspan="10" class="empty-state">No active employees found.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" style="text-align:right;">TOTALS:</td>
                        <td><?= formatCurrency($totSssEE) ?></td>
                        <td><?= formatCurrency($totSssER) ?></td>
                        <td><?= formatCurrency($totPhEE) ?></td>
                        <td><?= formatCurrency($totPhER) ?></td>
                        <td><?= formatCurrency($totPagibigEE) ?></td>
                        <td><?= formatCurrency($totPagibigER) ?></td>
                        <td>-</td>
                        <td style="color:var(--danger);"><?= formatCurrency($totTax) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- References -->
    <h3 style="font-size:16px;margin-bottom:16px;color:var(--text-main);">Rate Reference (2026 Policy)</h3>
    <div class="grid-3 mb-4">
        <div class="card"><div class="card-body">
            <h4 class="mb-2" style="color:var(--text-main);">SSS Contribution</h4>
            <p class="text-muted" style="font-size:12px;">Based on updated bracket (5% - 9.5%). Capped at ₱20,000 max salary credit.</p>
        </div></div>
        <div class="card"><div class="card-body">
            <h4 class="mb-2" style="color:var(--text-main);">PhilHealth Contribution</h4>
            <p class="text-muted" style="font-size:12px;">4% of monthly basic salary, split equally between EE and ER. Capped at ₱100,000 base.</p>
        </div></div>
        <div class="card"><div class="card-body">
            <h4 class="mb-2" style="color:var(--text-main);">Pag-IBIG Fund</h4>
            <p class="text-muted" style="font-size:12px;">Fixed ₱100 EE and ₱100 ER for salary ₱5,000 and above.</p>
        </div></div>
    </div>
    
    <div class="card">
        <div class="card-header"><h3>BIR TRAIN Law (2026 Brackets)</h3></div>
        <div class="card-body">
            <ul style="font-size:12px;color:var(--text-muted);line-height:2;">
                <li><strong>₱0 – ₱250,000:</strong> 0%</li>
                <li><strong>₱250,001 – ₱400,000:</strong> 15% of excess over ₱250,000</li>
                <li><strong>₱400,001 – ₱800,000:</strong> ₱22,500 + 20% of excess over ₱400,000</li>
                <li><strong>₱800,001 – ₱2,000,000:</strong> ₱102,500 + 25% of excess over ₱800,000</li>
                <li><strong>₱2,000,001 – ₱8,000,000:</strong> ₱402,500 + 30% of excess over ₱2,000,000</li>
                <li><strong>Over ₱8,000,000:</strong> ₱2,202,500 + 35% of excess over ₱8,000,000</li>
            </ul>
        </div>
    </div>

  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
