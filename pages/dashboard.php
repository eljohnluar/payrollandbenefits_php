<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'HR Analytics';
$currentPage = 'dashboard';
$pdo = getDB();

// 1. Total Employees
$totalEmp = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();

// 2. Active Employees
$activeEmp = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'")->fetchColumn();

// 3. On Leave Employees
$onLeaveEmp = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'On Leave'")->fetchColumn();

// 4. Resigned Employees
$resignedEmp = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Resigned'")->fetchColumn();

// 5. Latest payroll run
$latestRun = $pdo->query("SELECT period, total_gross, status FROM payroll_runs ORDER BY id DESC LIMIT 1")->fetch() ?: ['period' => 'N/A', 'total_gross' => 0, 'status' => 'N/A'];

// 6. Pending payroll items (Draft)
$pendingPayrollCount = $pdo->query("SELECT COUNT(*) FROM payroll_items WHERE status = 'Draft'")->fetchColumn();

// 7. Recent Payroll (Last 5)
$recentPayroll = $pdo->query("SELECT employee_name, net_pay, pay_date, status FROM payroll_items ORDER BY id DESC LIMIT 5")->fetchAll();

// 8. Recent Employees (Last 4)
$recentEmployees = $pdo->query("SELECT first_name, last_name, department, hire_date, status FROM employees ORDER BY created_at DESC LIMIT 4")->fetchAll();

// 9. Department Breakdown
$deptBreakdown = $pdo->query("SELECT department, COUNT(*) as emp_count, SUM(basic_salary) as total_sal FROM employees GROUP BY department ORDER BY total_sal DESC")->fetchAll();

// 10. Payroll Trend
$payrollTrend = $pdo->query("SELECT period, total_gross FROM payroll_runs ORDER BY id ASC LIMIT 7")->fetchAll();

// 11. Recent Audit Log
$recentAudit = $pdo->query("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 5")->fetchAll();

// 12. Claims Summary
$totalClaims = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
$pendingClaims = $pdo->query("SELECT COUNT(*) FROM claims WHERE status IN ('Pending', 'AI Review')")->fetchColumn();
$approvedClaims = $pdo->query("SELECT COUNT(*) FROM claims WHERE status IN ('Approved', 'Paid')")->fetchColumn();
$avgConfidence = $pdo->query("SELECT AVG(ai_confidence) FROM claims")->fetchColumn() ?: 0;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  
  <div class="page-content">
    <div class="page-header">
      <div>
        <h1>HR Analytics</h1>
        <p><?= date('l, M d, Y') ?></p>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Employees</div>
        <div class="stat-value"><?= number_format($totalEmp) ?></div>
        <div class="stat-change neutral">Across all departments</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Active Employees</div>
        <div class="stat-value"><?= number_format($activeEmp) ?></div>
        <div class="stat-change positive"><?= $onLeaveEmp ?> On Leave</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Total Payroll (<?= htmlspecialchars($latestRun['period']) ?>)</div>
        <div class="stat-value"><?= formatCurrency($latestRun['total_gross']) ?></div>
        <div class="stat-change <?= $latestRun['status'] === 'Paid' ? 'positive' : 'warning' ?>"><?= htmlspecialchars($latestRun['status']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">Pending Payroll Items</div>
        <div class="stat-value"><?= number_format($pendingPayrollCount) ?></div>
        <div class="stat-change warning">Requires approval</div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <a href="?page=employees" class="btn btn-secondary"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg> Manage Employees</a>
      <a href="?page=compensation" class="btn btn-secondary"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> Set Compensation</a>
      <a href="?page=payroll_run" class="btn btn-secondary"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg> Run Payroll</a>
      <a href="?page=payslips" class="btn btn-secondary"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Generate Payslips</a>
    </div>

    <div class="grid-2">
      <!-- Department Breakdown -->
      <div class="card mb-4">
        <div class="card-header">
          <h3>Department Breakdown</h3>
        </div>
        <div class="card-body">
          <?php 
          $maxSal = $deptBreakdown ? max(array_column($deptBreakdown, 'total_sal')) : 1;
          foreach ($deptBreakdown as $dept): 
            $pct = ($dept['total_sal'] / $maxSal) * 100;
          ?>
          <div style="margin-bottom: 12px;">
            <div class="flex justify-between items-center mb-4">
              <span class="font-semibold" style="font-size:13px;"><?= htmlspecialchars($dept['department']) ?> (<?= $dept['emp_count'] ?>)</span>
              <span class="text-muted"><?= formatCurrency($dept['total_sal']) ?></span>
            </div>
            <div class="progress-bar-track">
              <div class="progress-bar-fill" style="width: <?= $pct ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if(!$deptBreakdown) echo '<div class="empty-state">No department data found.</div>'; ?>
        </div>
      </div>

      <!-- Claims Summary -->
      <div class="card mb-4">
        <div class="card-header">
          <h3>Claims Summary</h3>
        </div>
        <div class="card-body">
          <div class="grid-2 mb-4">
            <div style="padding: 16px; background: var(--surface-alt); border-radius: var(--radius);">
              <div class="stat-label">Pending / Review</div>
              <div class="stat-value" style="color: var(--warning);"><?= number_format($pendingClaims) ?></div>
            </div>
            <div style="padding: 16px; background: var(--surface-alt); border-radius: var(--radius);">
              <div class="stat-label">Approved / Paid</div>
              <div class="stat-value" style="color: var(--success);"><?= number_format($approvedClaims) ?></div>
            </div>
          </div>
          <div class="flex justify-between items-center mt-4">
            <span class="text-muted">Total Claims Processed: <strong><?= number_format($totalClaims) ?></strong></span>
            <span class="badge badge-primary">Avg AI Confidence: <?= round($avgConfidence, 1) ?>%</span>
          </div>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <!-- Recent Payroll -->
      <div class="card">
        <div class="card-header">
          <h3>Recent Payroll Items</h3>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Employee</th>
                <th>Net Pay</th>
                <th>Pay Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentPayroll as $pr): ?>
              <tr>
                <td class="font-semibold"><?= htmlspecialchars($pr['employee_name']) ?></td>
                <td><?= formatCurrency($pr['net_pay']) ?></td>
                <td><?= formatDate($pr['pay_date']) ?: '-' ?></td>
                <td><span class="badge <?= getStatusBadgeClass($pr['status']) ?>"><?= htmlspecialchars($pr['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if(!$recentPayroll): ?>
              <tr><td colspan="4" class="empty-state">No recent payroll items found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Employees -->
      <div class="card">
        <div class="card-header">
          <h3>Recent Employees</h3>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Department</th>
                <th>Hire Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentEmployees as $emp): ?>
              <tr>
                <td class="font-semibold"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                <td><?= htmlspecialchars($emp['department']) ?></td>
                <td><?= formatDate($emp['hire_date']) ?></td>
                <td><span class="badge <?= getStatusBadgeClass($emp['status']) ?>"><?= htmlspecialchars($emp['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
              <?php if(!$recentEmployees): ?>
              <tr><td colspan="4" class="empty-state">No recent employees found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    
    <div class="grid-2" style="margin-top: 20px;">
        <!-- Recent Activity Feed -->
        <div class="card">
            <div class="card-header">
                <h3>Recent Activity Feed</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php foreach ($recentAudit as $log): ?>
                <div style="padding: 16px 20px; border-bottom: 1px solid var(--border);">
                    <div class="flex justify-between items-center mb-4">
                        <span class="font-semibold text-main"><?= htmlspecialchars($log['action']) ?></span>
                        <span class="text-muted" style="font-size: 11px;"><?= formatDate($log['created_at']) ?></span>
                    </div>
                    <p style="font-size: 13px; color: var(--text-muted); margin:0;"><?= htmlspecialchars($log['details']) ?></p>
                    <div style="font-size: 11px; margin-top: 8px; color: var(--text-muted);">By: <?= htmlspecialchars($log['user_name'] ?? 'System') ?></div>
                </div>
                <?php endforeach; ?>
                <?php if(!$recentAudit): ?>
                <div class="empty-state">No recent activity.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
