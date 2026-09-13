<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=my_benefits');
    exit;
}

verifyCsrf();
$action = $_POST['action'] ?? '';
$pdo = getDB();

if ($action === 'enroll') {
    try {
        $empId = $_POST['employee_id'] ?? '';
        $planId = $_POST['plan_id'] ?? '';
        $effDate = $_POST['effective_date'] ?? date('Y-m-d');
        $depCount = (int)($_POST['dependents'] ?? 0);
        
        $empStmt = $pdo->prepare("SELECT first_name, last_name FROM employees WHERE id = ?");
        $empStmt->execute([$empId]);
        $empRow = $empStmt->fetch();
        $empName = $empRow ? ($empRow['first_name'] . ' ' . $empRow['last_name']) : 'Unknown Employee';

        $plStmt = $pdo->prepare("SELECT plan_name, provider, monthly_premium, employer_share, employee_share FROM benefit_plans WHERE id = ?");
        $plStmt->execute([$planId]);
        $plRow = $plStmt->fetch();

        $erShare = $plRow['employer_share'] ?? 0;
        $eeShare = $plRow['employee_share'] ?? (100 - $erShare);

        $stmt = $pdo->prepare("INSERT INTO benefit_enrollments (employee_id, employee_name, plan_id, plan_name, provider, monthly_premium, employer_share, employee_share, dependents, effective_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([
            $empId,
            $empName,
            $planId,
            $plRow['plan_name'] ?? '',
            $plRow['provider'] ?? '',
            $plRow['monthly_premium'] ?? 0,
            $erShare,
            $eeShare,
            $depCount,
            $effDate
        ]);
        auditLog('Benefit Enrolled', "Enrolled employee {$empName} in plan " . ($plRow['plan_name'] ?? $planId));
        header('Location: ' . BASE_URL . '/index.php?page=my_benefits&msg=' . urlencode('Enrollment successful.'));
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        header('Location: ' . BASE_URL . '/index.php?page=my_benefits&error=' . urlencode($error));
        exit;
    }
} elseif ($action === 'cancel_enrollment') {
    try {
        $enrId = $_POST['enrollment_id'] ?? '';
        $pdo->prepare("UPDATE benefit_enrollments SET status='Cancelled' WHERE id=?")->execute([$enrId]);
        auditLog('Benefit Enrollment Cancelled', "Cancelled enrollment ID {$enrId}");
        header('Location: ' . BASE_URL . '/index.php?page=my_benefits&msg=' . urlencode('Enrollment cancelled.'));
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        header('Location: ' . BASE_URL . '/index.php?page=my_benefits&error=' . urlencode($error));
        exit;
    }
}

header('Location: ' . BASE_URL . '/index.php?page=my_benefits');
exit;
