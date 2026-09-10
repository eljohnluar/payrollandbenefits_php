<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Claim Tracker';
$currentPage = 'claim_tracker';
$pdo = getDB();

$claimId = $_GET['claim_id'] ?? '';

// Fetch all claims for tracking
$claims = $pdo->query("SELECT * FROM claims ORDER BY claim_date DESC")->fetchAll();

$c = null;
if ($claimId) {
    foreach($claims as $cl) {
        if ($cl['id'] == $claimId) { $c = $cl; break; }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header">
      <div>
        <h1>Claim Tracker</h1>
        <p>Track the real-time status and AI review of employee reimbursement claims</p>
      </div>
    </div>

    <div class="card" style="max-width:700px;">
        <div class="card-header">
            <select class="form-control" onchange="window.location.href='?page=claim_tracker&claim_id='+this.value" style="width:100%;">
                <option value="">-- Select an Employee Claim to Track --</option>
                <?php foreach($claims as $cl): ?>
                    <option value="<?= $cl['id'] ?>" <?= $claimId == $cl['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cl['claim_number']) ?> - <?= htmlspecialchars($cl['employee_name']) ?> - <?= htmlspecialchars($cl['category']) ?> (<?= formatCurrency($cl['amount']) ?>) - <?= htmlspecialchars($cl['status']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="card-body">
            <?php if($c): 
                // Determine timeline states
                $st = $c['status'];
                
                $t1 = 'done'; // Submitted
                
                $t2 = 'pending'; // AI
                if (in_array($st, ['AI Review', 'Approved', 'Rejected', 'Paid'])) $t2 = 'done';
                if ($st === 'Rejected') $t2 = 'rejected';
                
                $t3 = 'pending'; // HR Approval
                if (in_array($st, ['Approved', 'Paid'])) $t3 = 'done';
                if ($st === 'Rejected') $t3 = 'rejected';
                
                $t4 = 'pending'; // Disbursed
                if ($st === 'Paid') $t4 = 'done';
                
                $aiConf = $c['ai_confidence'];
            ?>
                <div class="flex justify-between items-center mb-4" style="background:var(--surface-alt);padding:16px;border-radius:var(--radius);">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:var(--text-main);margin-bottom:2px;">
                            👤 <?= htmlspecialchars($c['employee_name']) ?>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);text-transform:uppercase;margin-bottom:4px;">
                            <?= htmlspecialchars($c['category']) ?> &bull; <?= htmlspecialchars($c['claim_number']) ?>
                        </div>
                        <div style="font-size:24px;font-weight:700;color:var(--text-main);"><?= formatCurrency($c['amount']) ?></div>
                        <div style="font-size:12px;color:var(--primary);margin-top:4px;">AI Confidence: <?= $aiConf ?>%</div>
                    </div>
                    <span class="badge <?= getStatusBadgeClass($st) ?>"><?= htmlspecialchars($st) ?></span>
                </div>
                
                <div class="timeline" style="padding-left:16px;">
                    <!-- Step 1 -->
                    <div class="timeline-step">
                        <div class="timeline-line"></div>
                        <div class="timeline-circle <?= $t1 ?>">✓</div>
                        <div>
                            <div class="font-semibold text-main">Claim Submitted</div>
                            <div class="text-muted" style="font-size:12px;"><?= formatDate($c['claim_date']) ?></div>
                        </div>
                    </div>
                    <!-- Step 2 -->
                    <div class="timeline-step">
                        <div class="timeline-line"></div>
                        <div class="timeline-circle <?= $t2 ?>"><?= $t2==='done'?'✓':($t2==='rejected'?'✕':'2') ?></div>
                        <div>
                            <div class="font-semibold text-main">AI Review & Classification</div>
                            <div class="text-muted" style="font-size:12px;">
                                <?= $t2==='pending'?'Awaiting AI processing...':($t2==='rejected'?'Flagged by AI':'Processed with '.$aiConf.'% confidence') ?>
                            </div>
                        </div>
                    </div>
                    <!-- Step 3 -->
                    <div class="timeline-step">
                        <div class="timeline-line"></div>
                        <div class="timeline-circle <?= $t3 ?>"><?= $t3==='done'?'✓':($t3==='rejected'?'✕':'3') ?></div>
                        <div>
                            <div class="font-semibold text-main">HR Approval</div>
                            <div class="text-muted" style="font-size:12px;">
                                <?= $t3==='pending'?'Awaiting HR manager approval':($t3==='rejected'?'Claim was rejected':'Approved by HR') ?>
                            </div>
                        </div>
                    </div>
                    <!-- Step 4 -->
                    <div class="timeline-step">
                        <div class="timeline-line"></div>
                        <div class="timeline-circle <?= $t4 ?>"><?= $t4==='done'?'✓':'4' ?></div>
                        <div>
                            <div class="font-semibold text-main">Disbursement</div>
                            <div class="text-muted" style="font-size:12px;">
                                <?= $t4==='done'?'Funds transferred to employee\'s E-Wallet':'Awaiting payroll disbursement' ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">Select a claim to view its timeline tracking.</div>
            <?php endif; ?>
        </div>
    </div>
    
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
