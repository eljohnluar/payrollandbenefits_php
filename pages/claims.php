<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/claim_recipient_ai.php';
requireLogin();

$pageTitle = 'Claims Management';
$currentPage = 'claims';
$pdo = getDB();
ensureClaimRecipientSchema($pdo);

$statusFilter = $_GET['status_filter'] ?? 'All';
$selectedId = $_GET['selected_id'] ?? '';

$error = $_GET['error'] ?? null;
$msg = $_GET['msg'] ?? '';

// Build Query
$query = "SELECT * FROM claims WHERE 1=1";
$params = [];
if ($statusFilter !== 'All') {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
}
$query .= " ORDER BY claim_date DESC, id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$claims = $stmt->fetchAll();

// Get counts for filters
$countAll = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
$countPending = $pdo->query("SELECT COUNT(*) FROM claims WHERE status='Pending'")->fetchColumn();
$countAI = $pdo->query("SELECT COUNT(*) FROM claims WHERE status='AI Review'")->fetchColumn();
$countApproved = $pdo->query("SELECT COUNT(*) FROM claims WHERE status='Approved'")->fetchColumn();
$countRejected = $pdo->query("SELECT COUNT(*) FROM claims WHERE status='Rejected'")->fetchColumn();
$countPaid = $pdo->query("SELECT COUNT(*) FROM claims WHERE status='Paid'")->fetchColumn();

// Fetch selected
$selectedClaim = null;
if ($selectedId) {
    foreach ($claims as $c) {
        if ($c['id'] == $selectedId) {
            $selectedClaim = $c;
            break;
        }
    }
    // If not found in filtered list, fetch it directly
    if (!$selectedClaim) {
        $stmtS = $pdo->prepare("SELECT * FROM claims WHERE id=?");
        $stmtS->execute([$selectedId]);
        $selectedClaim = $stmtS->fetch();
    }
}
$receiptVerification = null;
if ($selectedClaim) {
    $verificationStmt = $pdo->prepare('SELECT * FROM claim_receipt_verifications WHERE claim_id = ?');
    $verificationStmt->execute([$selectedClaim['id']]);
    $receiptVerification = $verificationStmt->fetch();
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header">
      <div>
        <h1>Claims Management</h1>
        <p>Review and process employee reimbursement claims</p>
      </div>
      <div>
        <a href="?page=submit_claim" class="btn btn-primary">+ Log Claim</a>
      </div>
    </div>

    <?php if ($msg): ?><script>document.addEventListener('DOMContentLoaded', ()=>showToast('<?= htmlspecialchars($msg) ?>'));</script><?php endif; ?>

    <form class="filters-bar" method="GET">
        <input type="hidden" name="page" value="claims">
        <?php if($selectedId): ?><input type="hidden" name="selected_id" value="<?= $selectedId ?>"><?php endif; ?>
        <select name="status_filter" class="form-control" onchange="this.form.submit()" style="width:200px;">
            <option value="All" <?= $statusFilter==='All'?'selected':'' ?>>All Claims (<?= $countAll ?>)</option>
            <option value="Pending" <?= $statusFilter==='Pending'?'selected':'' ?>>Pending (<?= $countPending ?>)</option>
            <option value="AI Review" <?= $statusFilter==='AI Review'?'selected':'' ?>>AI Review (<?= $countAI ?>)</option>
            <option value="Approved" <?= $statusFilter==='Approved'?'selected':'' ?>>Approved (<?= $countApproved ?>)</option>
            <option value="Paid" <?= $statusFilter==='Paid'?'selected':'' ?>>Paid (<?= $countPaid ?>)</option>
            <option value="Rejected" <?= $statusFilter==='Rejected'?'selected':'' ?>>Rejected (<?= $countRejected ?>)</option>
        </select>
    </form>

    <div class="grid-2" style="grid-template-columns: 1fr 380px;">
        <!-- Left: Claims List -->
        <div class="card">
            <div class="table-wrap" style="max-height:600px;overflow-y:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>AI Conf.</th>
                            <th>Fraud Risk</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($claims as $c): 
                            $confColor = $c['ai_confidence'] >= 80 ? 'var(--success)' : ($c['ai_confidence'] >= 60 ? 'var(--warning)' : 'var(--danger)');
                            $fraudColor = $c['ai_fraud_score'] < 20 ? 'var(--success)' : ($c['ai_fraud_score'] < 40 ? 'var(--warning)' : 'var(--danger)');
                        ?>
                        <tr style="<?= $selectedId == $c['id'] ? 'background:var(--surface-alt);' : '' ?>">
                            <td class="font-semibold text-main"><?= htmlspecialchars($c['employee_name']) ?></td>
                            <td><?= htmlspecialchars($c['category']) ?></td>
                            <td class="font-semibold text-main"><?= formatCurrency($c['amount']) ?></td>
                            <td style="color:<?= $confColor ?>;font-weight:600;"><?= $c['ai_confidence'] ?>%</td>
                            <td style="color:<?= $fraudColor ?>;font-weight:600;"><?= $c['ai_fraud_score'] ?>%</td>
                            <td><span class="badge <?= getStatusBadgeClass($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                            <td>
                                <a href="?page=claims&status_filter=<?= urlencode($statusFilter) ?>&selected_id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(!$claims): ?><tr><td colspan="7" class="empty-state">No claims found.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right: Claim Detail & AI Panel -->
        <div class="card">
            <div class="card-header"><h3>Claim Detail</h3></div>
            <div class="card-body">
                <?php if($selectedClaim): 
                    $c = $selectedClaim;
                    $confColor = $c['ai_confidence'] >= 80 ? 'var(--success)' : ($c['ai_confidence'] >= 60 ? 'var(--warning)' : 'var(--danger)');
                    $fraudColor = $c['ai_fraud_score'] < 20 ? 'var(--success)' : ($c['ai_fraud_score'] < 40 ? 'var(--warning)' : 'var(--danger)');
                    $receiptScore = (int) ($c['receipt_verification_score'] ?? 0);
                    $receiptColor = $receiptScore >= 90 ? 'var(--success)' : ($receiptScore >= 70 ? 'var(--info)' : ($receiptScore >= 50 ? 'var(--warning)' : 'var(--danger)'));
                    $autoRec = $c['status'] === 'Approved' && $receiptScore >= 70;
                    $receiptIssues = $receiptVerification && $receiptVerification['issues'] ? (json_decode($receiptVerification['issues'], true) ?: []) : [];
                ?>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">
                        <div>
                            <div class="font-bold text-main" style="font-size:18px;"><?= formatCurrency($c['amount']) ?></div>
                            <div class="text-muted" style="font-size:13px;"><?= htmlspecialchars($c['claim_number']) ?></div>
                        </div>
                        <span class="badge <?= getStatusBadgeClass($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span>
                    </div>

                    <div style="font-size:13px;margin-bottom:24px;">
                        <div class="flex justify-between mb-2">
                            <span class="text-muted">Employee:</span>
                            <span class="font-semibold text-main"><?= htmlspecialchars($c['employee_name']) ?></span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span class="text-muted">Recipient AI:</span>
                            <span class="font-semibold text-main"><?= htmlspecialchars($c['recipient_method'] ?? 'Not recorded') ?> (<?= (int)($c['recipient_confidence'] ?? 0) ?>%)</span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span class="text-muted">HR decision:</span>
                            <span class="badge <?= ($c['recipient_decision'] ?? '') === 'Accepted' ? 'badge-success' : 'badge-warning' ?>"><?= htmlspecialchars($c['recipient_decision'] ?? 'Not recorded') ?></span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span class="text-muted">Category:</span>
                            <span class="font-semibold text-main"><?= htmlspecialchars($c['category']) ?></span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span class="text-muted">Date:</span>
                            <span class="font-semibold text-main"><?= formatDate($c['claim_date']) ?></span>
                        </div>
                        <div class="mt-4">
                            <div class="text-muted mb-1">Description:</div>
                            <div style="background:var(--surface-alt);padding:10px;border-radius:4px;color:var(--text-main);"><?= nl2br(htmlspecialchars($c['description'])) ?></div>
                        </div>
                        <?php if(!empty($c['receipt_file'])): ?>
                        <div class="mt-4">
                            <a href="?page=receipt_download&id=<?= (int) $c['id'] ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm btn-block">📎 View Receipt</a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- AI Analysis Panel -->
                    <div style="border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;">
                        <div style="background:var(--surface-alt);padding:12px 16px;border-bottom:1px solid var(--border);font-weight:600;display:flex;align-items:center;gap:8px;">
                            <span style="font-size:16px;">🤖</span> AI Analysis
                        </div>
                        <div style="padding:16px;">
                            <div class="mb-4">
                                <div class="flex justify-between items-center mb-1 text-sm">
                                    <span>Classification Confidence</span>
                                    <span style="color:<?= $confColor ?>;font-weight:700;"><?= $c['ai_confidence'] ?>%</span>
                                </div>
                                <div class="ai-bar"><div class="ai-bar-fill" style="width:<?= $c['ai_confidence'] ?>%;background:<?= $confColor ?>;"></div></div>
                            </div>
                            <div class="mb-4">
                                <div class="flex justify-between items-center mb-1 text-sm">
                                    <span>Fraud Risk Score</span>
                                    <span style="color:<?= $fraudColor ?>;font-weight:700;"><?= $c['ai_fraud_score'] ?>%</span>
                                </div>
                                <div class="ai-bar"><div class="ai-bar-fill" style="width:<?= $c['ai_fraud_score'] ?>%;background:<?= $fraudColor ?>;"></div></div>
                            </div>
                            <div class="mb-4">
                                <div class="flex justify-between items-center mb-1 text-sm">
                                    <span>Receipt Verification</span>
                                    <span style="color:<?= $receiptColor ?>;font-weight:700;"><?= $receiptScore ?>%</span>
                                </div>
                                <div class="ai-bar"><div class="ai-bar-fill" style="width:<?= $receiptScore ?>%;background:<?= $receiptColor ?>;"></div></div>
                                <div class="text-muted" style="font-size:12px;margin-top:6px;"><?= htmlspecialchars($c['receipt_verification_status'] ?? 'Not verified') ?></div>
                            </div>
                            <?php if ($receiptVerification): ?>
                            <details style="margin:0 0 16px;padding:10px;background:var(--surface-alt);border-radius:var(--radius);font-size:12px;">
                                <summary style="cursor:pointer;font-weight:600;">Receipt findings</summary>
                                <div style="margin-top:10px;line-height:1.7;">
                                    <div><span class="text-muted">Merchant:</span> <?= htmlspecialchars($receiptVerification['extracted_merchant'] ?: 'Not extracted') ?></div>
                                    <div><span class="text-muted">Receipt date:</span> <?= htmlspecialchars($receiptVerification['extracted_receipt_date'] ?: 'Not extracted') ?></div>
                                    <div><span class="text-muted">Receipt total:</span> <?= $receiptVerification['extracted_amount'] !== null ? formatCurrency((float) $receiptVerification['extracted_amount']) : 'Not extracted' ?></div>
                                    <div><span class="text-muted">OR / TIN:</span> <?= htmlspecialchars(($receiptVerification['extracted_or_number'] ?: 'Not extracted') . ' / ' . ($receiptVerification['extracted_tin'] ?: 'Not extracted')) ?></div>
                                    <div><span class="text-muted">Checks:</span> <?= $receiptVerification['image_readable'] ? 'Readable' : 'Low readability' ?> · <?= $receiptVerification['amount_matches'] ? 'Amount matched' : 'Amount unverified' ?> · <?= $receiptVerification['date_within_period'] ? 'Date matched' : 'Date unverified' ?></div>
                                    <?php foreach ($receiptIssues as $issue): ?><div style="color:var(--warning);">⚠ <?= htmlspecialchars($issue) ?></div><?php endforeach; ?>
                                </div>
                            </details>
                            <?php endif; ?>
                            
                            <div style="padding:10px;border-radius:var(--radius);font-size:12px;font-weight:600;display:flex;align-items:center;gap:8px;
                                        background:<?= $autoRec ? 'rgba(22,163,74,0.1)' : 'rgba(217,119,6,0.1)' ?>;
                                        color:<?= $autoRec ? 'var(--success)' : 'var(--warning)' ?>;">
                                <?php if($autoRec): ?>
                                    ✅ Auto-Approval Recommended
                                <?php else: ?>
                                    ⚠️ Manual Review Required
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <?php if($c['status'] === 'Pending' || $c['status'] === 'AI Review'): ?>
                    <div class="flex gap-2 mt-4 pt-4" style="border-top:1px solid var(--border);">
                        <form method="POST" action="<?= BASE_URL ?>/api/claims.php" style="flex:1;">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="approve_claim">
                            <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="status_filter" value="<?= htmlspecialchars($statusFilter) ?>">
                            <button type="submit" class="btn btn-success w-full" style="justify-content:center;">Approve</button>
                        </form>
                        <form method="POST" action="<?= BASE_URL ?>/api/claims.php" style="flex:1;">
                            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                            <input type="hidden" name="action" value="reject_claim">
                            <input type="hidden" name="claim_id" value="<?= $c['id'] ?>">
                            <input type="hidden" name="status_filter" value="<?= htmlspecialchars($statusFilter) ?>">
                            <button type="submit" class="btn btn-danger w-full" style="justify-content:center;" onclick="return confirm('Reject this claim?')">Reject</button>
                        </form>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <div style="font-size:48px;margin-bottom:16px;">🧠</div>
                        <p>Select a claim to view details and AI analysis.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
