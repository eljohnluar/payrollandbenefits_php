<?php
$user = getCurrentUser();
// Fetch unread notification count
try {
    $pdo   = getDB();
    $notif = $pdo->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn();
} catch (Throwable $e) { $notif = 0; }
?>
<header class="topbar">
  <div>
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></div>
    <div class="topbar-meta"><?= htmlspecialchars($pageSubtitle ?? COMPANY_NAME) ?></div>
  </div>
  <div class="topbar-actions">
    <!-- Notification bell -->
    <div style="position:relative;">
      <button class="btn btn-ghost btn-sm" id="notifBtn" style="position:relative;padding:6px 8px;">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
        </svg>
        <?php if ($notif > 0): ?>
        <span style="position:absolute;top:2px;right:2px;width:8px;height:8px;background:var(--danger);border-radius:50%;"></span>
        <?php endif; ?>
      </button>
      <div class="notif-dropdown" id="notifDropdown" style="display:none;position:absolute;right:0;top:44px;width:300px;background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);z-index:500;box-shadow:var(--shadow-lg);">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);font-size:13px;font-weight:600;color:var(--text-main);display:flex;justify-content:space-between;align-items:center;">
          Notifications
          <button onclick="markAllRead()" class="btn btn-ghost btn-sm" style="font-size:11px;">Mark all read</button>
        </div>
        <div style="max-height:280px;overflow-y:auto;" id="notifList">
          <?php
          try {
              $stmt = getDB()->query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10');
              $notifs = $stmt->fetchAll();
              if ($notifs):
                  foreach ($notifs as $n): ?>
                  <div style="padding:10px 16px;border-bottom:1px solid var(--border);background:<?= $n['is_read'] ? 'transparent' : 'rgba(16,185,129,.06)' ?>">
                    <div style="font-size:12px;color:var(--text-main);"><?= htmlspecialchars($n['message']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= formatDate($n['created_at']) ?></div>
                  </div>
          <?php endforeach; else: ?>
                  <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:12px;">No notifications</div>
          <?php endif;
          } catch (Exception $e) { ?>
                  <div style="padding:20px;text-align:center;color:var(--danger);font-size:12px;">Notifications unavailable</div>
          <?php } ?>
        </div>
      </div>
    </div>
    <!-- User avatar -->
    <div class="topbar-user">
      <div class="topbar-avatar"><?= htmlspecialchars($user['initials'] ?? 'HU') ?></div>
      <span><?= htmlspecialchars($user['name'] ?? 'HR User') ?></span>
    </div>
  </div>
</header>
