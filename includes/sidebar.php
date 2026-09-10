<?php
$navCurrent = $currentPage ?? currentPage();
$user       = getCurrentUser();

$navItems = [
    'HR CORE' => [
        ['page' => 'dashboard',    'label' => 'HR Analytics',      'icon' => '<path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
        ['page' => 'employees',    'label' => 'Employees',         'icon' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>'],
        ['page' => 'compensation', 'label' => 'Compensation',      'icon' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>'],
        ['page' => 'attendance',   'label' => 'Attendance',        'icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><polyline points="9 16 11 18 15 14"/>'],
        ['page' => 'payroll_run',  'label' => 'Payroll Run',       'icon' => '<rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/>'],
        ['page' => 'tax',          'label' => 'Tax & Contributions','icon' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>'],
        ['page' => 'payslips',     'label' => 'Payslips',          'icon' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>'],
    ],
    'CLAIMS & BENEFITS' => [
        ['page' => 'claims',           'label' => 'Claims',           'icon' => '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1" ry="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/>'],
        ['page' => 'submit_claim',     'label' => 'Log Claim',        'icon' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>'],
        ['page' => 'claim_tracker',    'label' => 'Claim Tracker',    'icon' => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
        ['page' => 'my_benefits',      'label' => 'Benefits',         'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
        ['page' => 'benefit_plans',    'label' => 'Benefit Plans',    'icon' => '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1" ry="1"/>'],
        ['page' => 'leave_balance',    'label' => 'Leave Balance',    'icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
        ['page' => 'leave_conversion', 'label' => 'Leave Conversion', 'icon' => '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/>'],
        ['page' => 'thirteenth_month', 'label' => '13th Month Pay',   'icon' => '<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/>'],
        ['page' => 'payslips_viewer',  'label' => 'Payslips Viewer',  'icon' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>'],
    ],
];
?>
<nav class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <svg width="18" height="18" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
        <line x1="1" y1="10" x2="23" y2="10"/>
      </svg>
    </div>
    <div>
      <div class="brand-name">HR System</div>
      <div class="brand-sub">Analytics</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($navItems as $section => $links): ?>
    <div class="sidebar-section">
      <div class="sidebar-section-label"><?= $section ?></div>
      <?php foreach ($links as $item): ?>
      <a href="<?= BASE_URL ?>/index.php?page=<?= $item['page'] ?>"
         class="nav-link <?= $navCurrent === $item['page'] ? 'active' : '' ?>">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
          <?= $item['icon'] ?>
        </svg>
        <?= htmlspecialchars($item['label']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="topbar-user" style="margin-bottom:10px;">
      <div class="topbar-avatar"><?= htmlspecialchars($user['initials'] ?? 'HU') ?></div>
      <div>
        <div style="font-size:13px;font-weight:600;color:var(--text-main)"><?= htmlspecialchars($user['name'] ?? 'HR User') ?></div>
        <div style="font-size:11px;color:var(--text-muted)"><?= htmlspecialchars($user['role'] ?? 'HR') ?></div>
      </div>
    </div>
    <a href="<?= BASE_URL ?>/index.php?page=logout" class="btn btn-secondary btn-sm w-full"
       onclick="return confirm('Are you sure you want to log out?')">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Logout
    </a>
  </div>
</nav>
