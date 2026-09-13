<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=leave_conversion');
    exit;
}

verifyCsrf();
$action = $_POST['action'] ?? '';
$pdo = getDB();
$user = getCurrentUser();

if ($action === 'process_conversion') {
    $type = trim($_POST['leave_type'] ?? '');
    $days = filter_var($_POST['days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $reason = trim($_POST['reason'] ?? '');

    if ($type === '' || $days === false || $reason === '') {
        $error = 'Please provide a leave type, a whole number of days, and a reason.';
        header('Location: ' . BASE_URL . '/index.php?page=leave_conversion&error=' . urlencode($error));
        exit;
    }

    $rate = 2045.45; // Hardcoded daily rate from specs for conversion
    $amt = $days * $rate;
    
    try {
        $pdo->beginTransaction();
        // Lock the balance while validating it so simultaneous requests cannot overspend it.
        $stmtB = $pdo->prepare('SELECT accrued, used FROM leave_balances WHERE leave_type = ? AND employee_id IS NULL FOR UPDATE');
        $stmtB->execute([$type]);
        $bal = $stmtB->fetch();

        if (!$bal) {
            throw new RuntimeException('The selected leave balance was not found.');
        }

        $avail = $bal['accrued'] - $bal['used'];
        if ($days > $avail) {
            throw new RuntimeException('Requested days exceed available balance.');
        }

        // Insert conversion
        $stmtC = $pdo->prepare("INSERT INTO leave_conversions (employee_id, leave_type, days, daily_rate, amount, conv_date, status, reason) VALUES (?, ?, ?, ?, ?, CURRENT_DATE, 'Pending', ?)");
        $stmtC->execute([$user['id'], $type, $days, $rate, $amt, $reason]);
        
        // Deduct balance
        $stmtU = $pdo->prepare('UPDATE leave_balances SET used = used + ?, balance = accrued - used WHERE leave_type = ? AND employee_id IS NULL');
        $stmtU->execute([$days, $type]);
        
        auditLog('Leave Conversion Requested', "Requested {$days} days of {$type} leave");
        $pdo->commit();
        header('Location: ' . BASE_URL . '/index.php?page=leave_conversion&msg=' . urlencode('Conversion request submitted successfully.'));
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
        header('Location: ' . BASE_URL . '/index.php?page=leave_conversion&error=' . urlencode($error));
        exit;
    }
}

header('Location: ' . BASE_URL . '/index.php?page=leave_conversion');
exit;
