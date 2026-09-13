<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=payslips_viewer');
    exit;
}

verifyCsrf();
$action = $_POST['action'] ?? '';
$pdo = getDB();

if ($action === 'delete_payslip') {
    $payslipId = filter_input(INPUT_POST, 'payslip_id', FILTER_VALIDATE_INT);

    if (!$payslipId) {
        http_response_code(400);
        die('Invalid payslip selected.');
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM payroll_items WHERE id = ?');
        $stmt->execute([$payslipId]);
        auditLog('Payslip Deleted', "Deleted payroll item ID {$payslipId}");

        $yearParam = urlencode((string) ($_POST['year'] ?? date('Y')));
        header('Location: ' . BASE_URL . "/index.php?page=payslips_viewer&year={$yearParam}&msg=" . urlencode('Payslip deleted successfully.'));
        exit;
    } catch (Exception $e) {
        $error = "Error deleting payslip: " . $e->getMessage();
        $yearParam = urlencode((string) ($_POST['year'] ?? date('Y')));
        header('Location: ' . BASE_URL . "/index.php?page=payslips_viewer&year={$yearParam}&error=" . urlencode($error));
        exit;
    }
}

header('Location: ' . BASE_URL . '/index.php?page=payslips_viewer');
exit;
