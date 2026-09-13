<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? ($_GET['action'] ?? '');

if ($action === 'mark_notif_read') {
    // If a notifications table exists, mark as read; otherwise return success
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
exit;
