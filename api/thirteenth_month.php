<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=thirteenth_month');
    exit;
}

verifyCsrf();
$action = $_POST['action'] ?? '';
$pdo = getDB();
$msg = '';

try {
    if ($action === 'approve') {
        $pdo->prepare("UPDATE thirteenth_month SET status='Approved' WHERE id=?")->execute([$_POST['record_id']]);
        auditLog('13th Month Approved', "Approved record {$_POST['record_id']}");
        $msg = "Approved successfully.";
    } elseif ($action === 'mark_paid') {
        $pdo->prepare("UPDATE thirteenth_month SET status='Paid', payment_date=CURRENT_DATE WHERE id=?")->execute([$_POST['record_id']]);
        auditLog('13th Month Paid', "Marked record {$_POST['record_id']} as paid");
        $msg = "Marked as paid.";
    }
    header("Location: " . BASE_URL . "/index.php?page=thirteenth_month&msg=" . urlencode($msg));
    exit;
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
    header("Location: " . BASE_URL . "/index.php?page=thirteenth_month&error=" . urlencode($error));
    exit;
}
