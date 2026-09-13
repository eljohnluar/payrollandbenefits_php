<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=claims');
    exit;
}

verifyCsrf();
$action = $_POST['action'] ?? '';
$claimId = $_POST['claim_id'] ?? '';
$statusFilter = $_POST['status_filter'] ?? ($_GET['status_filter'] ?? 'All');
$pdo = getDB();
$msg = '';

if ($claimId) {
    if ($action === 'approve_claim') {
        $pdo->prepare("UPDATE claims SET status='Approved' WHERE id=?")->execute([$claimId]);
        auditLog('Claim Approved', "Approved claim ID {$claimId}");
        $msg = "Claim approved.";
    } elseif ($action === 'reject_claim') {
        $pdo->prepare("UPDATE claims SET status='Rejected' WHERE id=?")->execute([$claimId]);
        auditLog('Claim Rejected', "Rejected claim ID {$claimId}");
        $msg = "Claim rejected.";
    }
    header("Location: " . BASE_URL . "/index.php?page=claims&status_filter=" . urlencode($statusFilter) . "&selected_id={$claimId}&msg=" . urlencode($msg));
    exit;
}

header("Location: " . BASE_URL . "/index.php?page=claims&status_filter=" . urlencode($statusFilter));
exit;
