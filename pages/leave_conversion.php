<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Leave Conversion';
$currentPage = 'leave_conversion';
$pdo = getDB();

$user = getCurrentUser();

$error = $_GET['error'] ?? null;
$msg = $_GET['msg'] ?? '';

// Fetch balances
$stmt = $pdo->prepare('SELECT * FROM leave_balances WHERE employee_id IS NULL AND (accrued - used) > 0');
$stmt->execute();
$availableLeaves = $stmt->fetchAll();

// Fetch history
$history = $pdo->query('SELECT * FROM leave_conversions ORDER BY conv_date DESC')->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header">
      <div>
        <h1>Leave to Cash Conversion</h1>
        <p>Convert unused leave credits to cash equivalent</p>
      </div>
    </div>

    <?php if (isset($error)): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($msg): ?><script>document.addEventListener('DOMContentLoaded', ()=>showToast('<?= htmlspecialchars($msg) ?>'));</script><?php endif; ?>

    <div class="grid-2">
        <!-- Process Form -->
        <div class="card">
            <div class="card-header"><h3>Process Conversion</h3></div>
            <div class="card-body">
                <div style="background:rgba(2,132,199,0.1);border-left:3px solid var(--info);padding:12px;border-radius:0 4px 4px 0;margin-bottom:20px;font-size:12px;color:var(--text-main);">
                    <strong>Policy Info:</strong> Unused Vacation and Sick Leaves can be converted to cash at the end of the year. Current conversion rate is based on <strong>₱2,045.45/day</strong>.
                </div>
                
                <form method="POST" action="<?= BASE_URL ?>/api/leave_conversion.php">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="action" value="process_conversion">
                    
                    <div class="form-group">
                        <label class="form-label required">Leave Type</label>
                        <select name="leave_type" id="l_type" class="form-control" required onchange="calcAmt()">
                            <option value="">Select Leave Type</option>
                            <?php foreach($availableLeaves as $al): 
                                $bal = $al['accrued'] - $al['used'];
                            ?>
                                <option value="<?= htmlspecialchars($al['leave_type']) ?>" data-max="<?= $bal ?>">
                                    <?= htmlspecialchars($al['leave_type']) ?> Leave (<?= $bal ?> days avail.)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Days to Convert</label>
                            <input type="number" step="1" min="1" name="days" id="l_days" class="form-control" required oninput="calcAmt()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estimated Cash Amount</label>
                            <input type="text" id="l_amt" class="form-control" readonly style="background:var(--surface-alt);color:var(--success);font-weight:700;">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Reason / Remarks</label>
                        <textarea name="reason" class="form-control" rows="2" required placeholder="e.g. Annual leave conversion 2026"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block mt-4">Submit Request</button>
                </form>
            </div>
        </div>

        <!-- History Table -->
        <div class="card">
            <div class="card-header"><h3>Conversion History</h3></div>
            <div class="table-wrap" style="max-height:500px;overflow-y:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Days</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($history as $h): ?>
                        <tr>
                            <td><?= formatDate($h['conv_date']) ?></td>
                            <td class="font-semibold"><?= htmlspecialchars($h['leave_type']) ?></td>
                            <td><?= (int)$h['days'] ?></td>
                            <td class="font-semibold text-main"><?= formatCurrency($h['amount']) ?></td>
                            <td><span class="badge <?= getStatusBadgeClass($h['status']) ?>"><?= htmlspecialchars($h['status']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$history): ?><tr><td colspan="5" class="empty-state">No conversion history found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

  </div>
</div>

<script>
function calcAmt() {
    const rate = 2045.45;
    const daysInput = document.getElementById('l_days');
    const typeSel = document.getElementById('l_type');
    let days = parseFloat(daysInput.value || 0);
    
    // enforce max
    if (typeSel.selectedIndex > 0) {
        const max = parseFloat(typeSel.options[typeSel.selectedIndex].dataset.max || 0);
        if (days > max) { days = max; daysInput.value = max; }
    }
    
    const amt = days * rate;
    document.getElementById('l_amt').value = amt > 0 ? formatPHP(amt) : '';
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
