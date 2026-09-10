<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$pageTitle = 'Benefit Plans';
$currentPage = 'benefit_plans';
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_plan') {
    verifyCsrf();
    $stmt = $pdo->prepare("UPDATE benefit_plans SET monthly_premium=?, employer_share=? WHERE id=?");
    $stmt->execute([$_POST['monthly_premium'], $_POST['employer_share'], $_POST['plan_id']]);
    header('Location: ' . BASE_URL . '/index.php?page=benefit_plans&msg=' . urlencode('Plan updated.'));
    exit;
}
$msg = $_GET['msg'] ?? '';

$plans = $pdo->query("SELECT * FROM benefit_plans ORDER BY id")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    
    <div class="page-header">
      <div>
        <h1>Manage Benefit Plans</h1>
        <p>Company-provided healthcare, insurance, and retirement plans catalog</p>
      </div>
    </div>

    <?php if ($msg): ?><script>document.addEventListener('DOMContentLoaded', ()=>showToast('<?= htmlspecialchars($msg) ?>'));</script><?php endif; ?>

    <div class="grid-2">
        <?php foreach($plans as $p): 
            $icon = strpos(strtolower($p['plan_name']), 'hmo') !== false || strpos(strtolower($p['plan_name']), 'health') !== false ? '❤️' : 
                    (strpos(strtolower($p['plan_name']), 'insurance') !== false ? '🛡️' : '💰');
            $eePct = 100 - $p['employer_share'];
            $eeAmt = $p['monthly_premium'] * ($eePct/100);
            $erAmt = $p['monthly_premium'] * ($p['employer_share']/100);
        ?>
        <div class="card mb-4" style="display:flex;flex-direction:column;">
            <div class="card-header flex items-center gap-4">
                <div style="font-size:32px;background:var(--surface-alt);width:48px;height:48px;display:flex;align-items:center;justify-content:center;border-radius:var(--radius);"><?= $icon ?></div>
                <div>
                    <h3 style="font-size:16px;color:var(--text-main);margin-bottom:2px;"><?= htmlspecialchars($p['plan_name']) ?></h3>
                    <div class="text-muted" style="font-size:12px;"><?= htmlspecialchars($p['provider']) ?></div>
                </div>
            </div>
            <div class="card-body" style="flex:1;">
                <p class="text-muted" style="margin-bottom:20px;font-size:13px;line-height:1.6;">
                    <?= htmlspecialchars($p['description']) ?>
                </p>
                <div class="grid-2" style="background:var(--surface-alt);padding:16px;border-radius:var(--radius);">
                    <div style="border-right:1px solid var(--border);padding-right:16px;">
                        <div class="text-muted mb-1" style="font-size:11px;text-transform:uppercase;">Total Premium</div>
                        <div class="font-bold text-main" style="font-size:16px;"><?= formatCurrency($p['monthly_premium']) ?><span style="font-size:11px;color:var(--text-muted);font-weight:normal;">/mo</span></div>
                    </div>
                    <div style="padding-left:16px;">
                        <div class="flex justify-between mb-2" style="font-size:12px;">
                            <span class="text-muted">Company Share (<?= $p['employer_share'] ?>%)</span>
                            <strong style="color:var(--success)"><?= formatCurrency($erAmt) ?></strong>
                        </div>
                        <div class="flex justify-between" style="font-size:12px;">
                            <span class="text-muted">Employee Share (<?= $eePct ?>%)</span>
                            <strong style="color:var(--danger)"><?= formatCurrency($eeAmt) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-secondary w-full" onclick="managePlan(<?= htmlspecialchars(json_encode($p)) ?>)">Manage Plan Setup</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
  </div>
</div>

<div id="modalPlan" class="modal-backdrop" style="display:none;">
  <div class="modal-box">
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="save_plan">
      <input type="hidden" name="plan_id" id="pl_id">
      
      <div class="modal-header">
        <h3 id="pl_title">Manage Plan</h3>
        <button type="button" class="modal-close" onclick="closeModal('modalPlan')">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
            <label class="form-label required">Monthly Premium (Total)</label>
            <input type="number" step="0.01" name="monthly_premium" id="pl_prem" class="form-control" required oninput="previewShares()">
        </div>
        <div class="form-group">
            <label class="form-label required">Company Share %</label>
            <input type="number" name="employer_share" id="pl_share" class="form-control" min="0" max="100" required oninput="previewShares()">
        </div>
        <div style="margin-top:20px;padding:16px;background:var(--surface-alt);border-radius:var(--radius);border-left:3px solid var(--primary);">
            <div style="font-size:12px;color:var(--text-main);margin-bottom:4px;">Payroll Deduction Impact</div>
            <div class="text-muted" style="font-size:11px;">Employees enrolled in this plan will have <strong id="pl_ee_amt" style="color:var(--danger)">₱0.00</strong> automatically deducted from their monthly gross pay.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modalPlan')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function managePlan(p) {
    document.getElementById('pl_id').value = p.id;
    document.getElementById('pl_title').textContent = 'Manage: ' + p.plan_name;
    document.getElementById('pl_prem').value = p.monthly_premium;
    document.getElementById('pl_share').value = p.employer_share;
    previewShares();
    openModal('modalPlan');
}
function previewShares() {
    const prem = parseFloat(document.getElementById('pl_prem').value || 0);
    const er = parseFloat(document.getElementById('pl_share').value || 0);
    const eePct = Math.max(0, 100 - er);
    const eeAmt = prem * (eePct/100);
    document.getElementById('pl_ee_amt').textContent = formatPHP(eeAmt);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
