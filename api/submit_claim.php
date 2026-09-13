<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/claim_recipient_ai.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=submit_claim');
    exit;
}

verifyCsrf();
$pdo = getDB();
ensureClaimRecipientSchema($pdo);

$action = $_POST['action'] ?? '';

// 1. AJAX JSON Endpoint for recipient suggestions
if ($action === 'recipient_suggestions') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'suggestions' => getClaimRecipientSuggestions($pdo, (string) ($_POST['description'] ?? ''), getCurrentUser())
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Form Submission Endpoint
if ($action === 'submit_claim') {
    $categories = $pdo->query('SELECT name AS category_name, max_amount FROM claim_categories ORDER BY name')->fetchAll();
    $categoryLimits = array_column($categories, 'max_amount', 'category_name');

    $category = trim((string) ($_POST['category'] ?? ''));
    $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_FLOAT);
    $description = trim((string) ($_POST['description'] ?? ''));
    $claimDate = (string) ($_POST['claim_date'] ?? '');
    $employeeId = trim((string) ($_POST['employee_id'] ?? ''));
    $requestedDecision = (string) ($_POST['recipient_decision'] ?? 'Pending');
    $formError = '';

    if (!array_key_exists($category, $categoryLimits) || $amount === false || $amount <= 0 || !$description || !DateTime::createFromFormat('Y-m-d', $claimDate)) {
        $formError = 'Please provide a valid category, positive amount, receipt date, and description.';
    } elseif (!$employeeId && $requestedDecision !== 'Rejected') {
        $formError = 'Choose a recipient or reject the suggestion when this claim has no employee recipient.';
    } else {
        $selectedEmployee = null;
        if ($employeeId) {
            $stmt = $pdo->prepare("SELECT id, code, first_name, middle_name, last_name, suffix, department, position FROM employees WHERE id = ? AND status IN ('Active','On Leave')");
            $stmt->execute([$employeeId]);
            $selectedEmployee = $stmt->fetch();
            if (!$selectedEmployee) {
                $formError = 'The selected recipient is unavailable. Please choose another employee.';
            }
        }

        if (!$formError) {
            $suggestions = getClaimRecipientSuggestions($pdo, $description, getCurrentUser());
            $topSuggestion = $suggestions[0] ?? null;
            $selectedSuggestion = null;
            foreach ($suggestions as $suggestion) {
                if ($suggestion['id'] === $employeeId) {
                    $selectedSuggestion = $suggestion;
                    break;
                }
            }

            $decision = !$employeeId ? 'Rejected' : (($topSuggestion && $topSuggestion['id'] === $employeeId) ? 'Accepted' : 'Overridden');
            $recipientConfidence = $selectedSuggestion['confidence'] ?? 0;
            $recipientMethod = $selectedSuggestion['method'] ?? 'Manual selection';
            $employeeName = $selectedEmployee ? employeeDisplayName($selectedEmployee) : 'Unassigned recipient';

            try {
                $receipt = validateAndStoreReceipt($_FILES['receipt'] ?? []);
                $receiptAnalysis = analyzeReceipt($pdo, $receipt, (float) $amount, $claimDate, $employeeName, $category);
            } catch (Throwable $e) {
                if (isset($receipt['path']) && is_file($receipt['path'])) {
                    @unlink($receipt['path']);
                }
                $formError = $e->getMessage();
            }

            if (!$formError) {
                $descriptionLower = normalizeClaimText($description);
                $keywords = [
                    'Transportation' => ['grab', 'taxi', 'uber', 'jeep', 'bus', 'mrt', 'lrt', 'toll', 'parking', 'ride'],
                    'Meal Allowance' => ['meal', 'lunch', 'dinner', 'food', 'resto', 'restaurant', 'breakfast', 'snack', 'coffee'],
                    'Medical'        => ['medical', 'hospital', 'clinic', 'doctor', 'medicine', 'pharmacy', 'health', 'consult'],
                    'Office Supplies'=> ['supplies', 'paper', 'ink', 'printer', 'pen', 'notebook', 'office', 'stationery'],
                    'Training'       => ['training', 'seminar', 'workshop', 'conference', 'certification', 'course', 'learning'],
                    'Overtime'       => ['overtime', 'ot', 'extra hours', 'additional work'],
                ];
                $matches = count(array_filter($keywords[$category] ?? [], static fn($word) => str_contains($descriptionLower, $word)));
                $classificationConfidence = min(99, 55 + ($matches * 12) + (mb_strlen($description, 'UTF-8') > 20 ? 5 : 0));
                $fraud = 5;
                $maximum = (float) $categoryLimits[$category];
                if ($amount > $maximum * .9) $fraud += 20;
                if ($amount > $maximum) $fraud += 30;
                if (mb_strlen($description, 'UTF-8') < 10) $fraud += 25;
                foreach (['personal', 'home', 'vacation', 'shopping', 'gaming'] as $word) {
                    if (str_contains($descriptionLower, $word)) $fraud += 20;
                }
                $fraud = min(99, max($fraud, 100 - $receiptAnalysis['score']));
                $autoApproved = $decision === 'Accepted' && $recipientConfidence >= 80 && $classificationConfidence >= 80 && $receiptAnalysis['score'] >= 70 && $amount <= $maximum;
                $status = $receiptAnalysis['score'] < 30 ? 'Rejected' : ($autoApproved ? 'Approved' : 'AI Review');

                do {
                    $claimNumber = 'CLM' . date('Ymd') . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                    $exists = $pdo->prepare('SELECT 1 FROM claims WHERE claim_number = ?');
                    $exists->execute([$claimNumber]);
                } while ($exists->fetchColumn());

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('INSERT INTO claims (claim_number, employee_name, employee_id, category, amount, claim_date, description, status, ai_confidence, recipient_confidence, recipient_method, recipient_decision, ai_fraud_score, receipt_file, receipt_hash, receipt_mime, receipt_verification_score, receipt_verification_status, receipt_verification_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                    $stmt->execute([
                        $claimNumber, $employeeName, $employeeId ?: null, $category, $amount, $claimDate, $description, $status,
                        $classificationConfidence, $recipientConfidence, $recipientMethod, $decision, $fraud,
                        $receipt['file'], $receipt['hash'], $receipt['mime'],
                        $receiptAnalysis['score'], $receiptAnalysis['status'], implode(' ', $receiptAnalysis['issues']) ?: null
                    ]);
                    $claimId = (int) $pdo->lastInsertId();
                    saveReceiptVerification($pdo, $claimId, $receiptAnalysis);
                    $prediction = $pdo->prepare('INSERT INTO ai_predictions (claim_id, description_normalized, suggested_employee_id, selected_employee_id, recipient_confidence, match_method, recipient_decision, candidate_snapshot) VALUES (?,?,?,?,?,?,?,?)');
                    $prediction->execute([
                        $claimId, normalizeClaimText($description), $topSuggestion['id'] ?? null, $employeeId ?: null,
                        $topSuggestion['confidence'] ?? 0, $topSuggestion['method'] ?? 'No match', $decision,
                        json_encode($suggestions, JSON_UNESCAPED_UNICODE)
                    ]);
                    $pdo->commit();
                    auditLog('Claim Submitted', "Submitted claim {$claimNumber}; recipient {$employeeName}; decision {$decision}");
                    header('Location: ' . BASE_URL . '/index.php?page=submit_claim&show=result&claim_number=' . urlencode($claimNumber));
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (isset($receipt['path']) && is_file($receipt['path'])) {
                        @unlink($receipt['path']);
                    }
                    $formError = 'The claim could not be saved. Please try again.';
                }
            }
        }
    }

    header('Location: ' . BASE_URL . '/index.php?page=submit_claim&error=' . urlencode($formError));
    exit;
}

header('Location: ' . BASE_URL . '/index.php?page=submit_claim');
exit;
