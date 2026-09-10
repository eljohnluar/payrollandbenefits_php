<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) { http_response_code(404); exit('Receipt not found.'); }
$pdo = getDB();
$stmt = $pdo->prepare('SELECT receipt_file, receipt_mime FROM claims WHERE id = ?');
$stmt->execute([$id]);
$receipt = $stmt->fetch();
$file = $receipt['receipt_file'] ?? '';
if (!$file || !preg_match('/^[a-f0-9]{40}\.(jpg|png|webp|pdf)$/', $file)) { http_response_code(404); exit('Receipt not found.'); }
$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'receipts' . DIRECTORY_SEPARATOR . $file;
if (!is_file($path)) { http_response_code(404); exit('Receipt not found.'); }
header('Content-Type: ' . ($receipt['receipt_mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="receipt-' . $id . '.' . pathinfo($file, PATHINFO_EXTENSION) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
