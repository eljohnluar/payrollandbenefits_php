<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/claim_recipient_ai.php';
requireLogin();

$pageTitle = 'Log Claim';
$currentPage = 'submit_claim';
$pdo = getDB();
ensureClaimRecipientSchema($pdo);
$categories = $pdo->query('SELECT name AS category_name, max_amount FROM claim_categories ORDER BY name')->fetchAll();
$categoryLimits = array_column($categories, 'max_amount', 'category_name');

$formError = $_GET['error'] ?? '';

$employees = $pdo->query("SELECT id, code, first_name, middle_name, last_name, suffix, department, position FROM employees WHERE status IN ('Active','On Leave') ORDER BY first_name, last_name")->fetchAll();
$showResult = isset($_GET['show']) && $_GET['show'] === 'result';
$savedClaim = null;
if ($showResult && !empty($_GET['claim_number'])) {
    $stmt = $pdo->prepare('SELECT * FROM claims WHERE claim_number = ?');
    $stmt->execute([$_GET['claim_number']]);
    $savedClaim = $stmt->fetch();
    if (!$savedClaim) { $showResult = false; $formError = 'That claim could not be found.'; }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="app-main">
  <?php include __DIR__ . '/../includes/topbar.php'; ?>
  <div class="page-content">
    <div class="page-header"><div><h1>Log Employee Claim</h1><p>Describe the expense and AI will identify the likely recipient before review.</p></div></div>
    <div style="max-width:680px;margin:0 auto;">
      <?php if (!$showResult): ?>
      <div class="card" id="claimFormDiv"><div class="card-body">
        <div style="margin-bottom:24px;padding:12px;background:rgba(59,130,246,.1);border-left:3px solid var(--info);border-radius:0 4px 4px 0;font-size:12px;color:var(--text-main);"><strong>Recipient AI:</strong> Add a name, employee code, department, or role in the description. You can accept the suggestion, select another employee, or mark the claim as having no recipient.</div>
        <?php if ($formError): ?><div class="alert alert-danger" style="margin-bottom:16px;"><?= htmlspecialchars($formError) ?></div><?php endif; ?>
        <form id="realForm" method="POST" action="<?= BASE_URL ?>/api/submit_claim.php" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" id="claimCsrf" value="<?= csrfToken() ?>"><input type="hidden" name="action" value="submit_claim"><input type="hidden" name="employee_id" id="employeeId"><input type="hidden" name="recipient_decision" id="recipientDecision" value="Pending">
          <div class="form-group"><label class="form-label required">Description / Business Purpose</label><textarea id="claimDescription" name="description" class="form-control" rows="3" required placeholder="Example: Grab ride for Juan Dela Cruz to the client meeting"></textarea><small class="text-muted">AI checks as you type; no external service receives this information.</small></div>
          <div class="form-group"><label class="form-label">AI Recipient Suggestion</label><div id="recipientEmpty" style="padding:12px;border:1px dashed var(--border);border-radius:var(--radius);color:var(--text-muted);font-size:13px;">Enter a description to identify a recipient.</div><div id="recipientSuggestion" style="display:none;padding:14px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface-alt);"><div class="flex justify-between items-center"><div><div id="suggestionName" class="font-semibold text-main"></div><div id="suggestionMeta" class="text-muted" style="font-size:12px;"></div></div><span id="suggestionConfidence" class="badge badge-info"></span></div><div id="suggestionMethod" class="text-muted" style="font-size:12px;margin:8px 0 12px;"></div><div class="flex gap-2"><button type="button" class="btn btn-primary btn-sm" id="acceptSuggestion">Accept suggestion</button><button type="button" class="btn btn-secondary btn-sm" id="chooseDifferent">Choose another</button><button type="button" class="btn btn-ghost btn-sm" id="rejectSuggestion">No recipient</button></div></div></div>
          <div class="form-group" id="manualRecipientGroup"><label class="form-label">Recipient</label><select id="manualRecipient" class="form-control"><option value="">Search/select employee</option><?php foreach ($employees as $employee): $name = employeeDisplayName($employee); ?><option value="<?= htmlspecialchars($employee['id']) ?>"><?= htmlspecialchars($name . ' (' . $employee['code'] . ' · ' . $employee['department'] . ')') ?></option><?php endforeach; ?></select><small class="text-muted">Required when there is no accepted AI suggestion.</small></div>
          <div class="form-row"><div class="form-group"><label class="form-label required">Category</label><select name="category" class="form-control" required><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?= htmlspecialchars($category['category_name']) ?>"><?= htmlspecialchars($category['category_name']) ?> (Max: <?= formatCurrency($category['max_amount']) ?>)</option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label required">Amount (PHP)</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" required></div></div>
          <div class="form-group"><label class="form-label required">Receipt Date</label><input type="date" name="claim_date" class="form-control" max="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group"><label class="form-label required">Receipt</label><input id="receiptFile" type="file" name="receipt" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" required><small id="receiptHelp" class="text-muted">JPG, PNG, WEBP, or PDF; up to 10 MB. The receipt is checked for readability, duplicate use, date and amount consistency.</small></div>
          <div class="mt-4 pt-4" style="border-top:1px solid var(--border);"><button type="submit" class="btn btn-primary btn-block btn-lg">Submit Claim for AI Review</button></div>
        </form>
      </div></div>
      <?php else: $auto = $savedClaim['status'] === 'Approved'; $rejected = $savedClaim['status'] === 'Rejected'; ?>
      <div class="card"><div class="card-body" style="text-align:center;"><div style="font-size:64px;margin-bottom:16px;"><?= $auto ? '✅' : ($rejected ? '⛔' : '⚠️') ?></div><h2 style="font-size:20px;color:var(--text-main);margin-bottom:24px;"><?= $auto ? 'Claim Auto-Approved!' : ($rejected ? 'Claim Rejected by Receipt Verification' : 'Pending Manual Review') ?></h2><div style="text-align:left;background:var(--surface-alt);padding:16px;border-radius:var(--radius);margin-bottom:24px;"><div class="flex justify-between mb-2"><span class="text-muted">Recipient:</span><span class="font-semibold text-main"><?= htmlspecialchars($savedClaim['employee_name']) ?></span></div><div class="flex justify-between mb-2"><span class="text-muted">Recipient decision:</span><span class="badge <?= $savedClaim['recipient_decision'] === 'Accepted' ? 'badge-success' : 'badge-warning' ?>"><?= htmlspecialchars($savedClaim['recipient_decision']) ?></span></div><div class="flex justify-between mb-2"><span class="text-muted">Receipt verification:</span><span class="font-semibold text-main"><?= (int) $savedClaim['receipt_verification_score'] ?>% · <?= htmlspecialchars($savedClaim['receipt_verification_status']) ?></span></div><div class="flex justify-between mb-2"><span class="text-muted">Match:</span><span class="font-semibold text-main"><?= htmlspecialchars($savedClaim['recipient_method']) ?> (<?= (int) $savedClaim['recipient_confidence'] ?>%)</span></div><div class="flex justify-between"><span class="text-muted">Claim number:</span><span class="font-semibold text-main"><?= htmlspecialchars($savedClaim['claim_number']) ?></span></div></div><div class="flex gap-4"><a href="?page=submit_claim" class="btn btn-secondary" style="flex:1;justify-content:center;">Log Another Claim</a><a href="?page=claims&selected_id=<?= $savedClaim['id'] ?>" class="btn btn-primary" style="flex:1;justify-content:center;">View Claim</a></div></div></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php if (!$showResult): ?><script>
(() => {
  const description = document.getElementById('claimDescription'), csrf = document.getElementById('claimCsrf'), empty = document.getElementById('recipientEmpty'), panel = document.getElementById('recipientSuggestion'), select = document.getElementById('manualRecipient'), employeeId = document.getElementById('employeeId'), decision = document.getElementById('recipientDecision');
  let suggestions = [], timer;
  const choose = (candidate, type) => { employeeId.value = candidate ? candidate.id : ''; decision.value = type; if (candidate) select.value = candidate.id; };
  const showTop = () => { const top = suggestions[0]; if (!top) { panel.style.display = 'none'; empty.style.display = ''; empty.textContent = description.value.trim() ? 'No confident recipient found. Please choose an employee or mark no recipient.' : 'Enter a description to identify a recipient.'; return; } empty.style.display = 'none'; panel.style.display = ''; document.getElementById('suggestionName').textContent = top.name; document.getElementById('suggestionMeta').textContent = `${top.code} · ${top.department} · ${top.position}`; document.getElementById('suggestionConfidence').textContent = `${top.confidence}% confidence`; document.getElementById('suggestionMethod').textContent = top.method; choose(top, 'Accepted'); };
  async function analyze() { const text = description.value.trim(); if (text.length < 3) { suggestions = []; showTop(); return; } const data = new FormData(); data.append('action','recipient_suggestions'); data.append('csrf_token', csrf.value); data.append('description', text); try { const response = await fetch('<?= BASE_URL ?>/api/submit_claim.php', {method:'POST', body:data}); const payload = await response.json(); suggestions = payload.suggestions || []; showTop(); } catch (_) { empty.textContent = 'Recipient analysis is temporarily unavailable. Please select an employee manually.'; } }
  description.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(analyze, 350); });
  document.getElementById('acceptSuggestion').addEventListener('click', () => choose(suggestions[0], 'Accepted'));
  document.getElementById('chooseDifferent').addEventListener('click', () => { select.focus(); });
  document.getElementById('rejectSuggestion').addEventListener('click', () => { choose(null, 'Rejected'); select.value = ''; empty.style.display = ''; empty.textContent = 'Marked as no employee recipient.'; });
  select.addEventListener('change', () => { const candidate = suggestions.find(item => item.id === select.value); employeeId.value = select.value; decision.value = candidate && suggestions[0] && candidate.id === suggestions[0].id ? 'Accepted' : (select.value ? 'Overridden' : 'Pending'); });
  document.getElementById('receiptFile').addEventListener('change', event => { const file = event.target.files[0]; document.getElementById('receiptHelp').textContent = file ? `${file.name} selected. It will be securely analyzed when submitted.` : 'JPG, PNG, WEBP, or PDF; up to 10 MB.'; });
  document.getElementById('realForm').addEventListener('submit', event => { if (!employeeId.value && decision.value !== 'Rejected') { event.preventDefault(); select.focus(); alert('Choose a recipient or select “No recipient”.'); } });
})();
</script><?php endif; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
