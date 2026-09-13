<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=employees');
    exit;
}

verifyCsrf();
$action = $_POST['action'] ?? '';
$pdo = getDB();

if ($action === 'create_employee') {
    try {
        $pdo->beginTransaction();
        
        // Generate next code
        $lastCode = $pdo->query("SELECT code FROM employees ORDER BY id DESC LIMIT 1")->fetchColumn();
        $nextNum = 1;
        if ($lastCode && preg_match('/EMP-\d{4}-(\d+)/', $lastCode, $matches)) {
            $nextNum = intval($matches[1]) + 1;
        }
        $code = 'EMP-' . date('Y') . '-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
        $id = 'emp-' . uniqid();
        
        $stmt = $pdo->prepare("
            INSERT INTO employees 
            (id, code, first_name, middle_name, last_name, suffix, email, mobile, birth_date, gender, department, position, employment_type, hire_date, basic_salary, status, sss, philhealth, pagibig, tin, ewallet_provider, ewallet_account, ewallet_name) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $id,
            $code,
            $_POST['first_name'],
            $_POST['middle_name'] ?: null,
            $_POST['last_name'],
            $_POST['suffix'] ?: null,
            $_POST['email'] ?: null,
            $_POST['mobile'] ?: null,
            $_POST['birth_date'] ?: null,
            $_POST['gender'] ?: null,
            $_POST['department'],
            $_POST['position'],
            $_POST['employment_type'],
            $_POST['hire_date'],
            $_POST['basic_salary'] ?: 0,
            $_POST['status'],
            $_POST['sss'] ?: null,
            $_POST['philhealth'] ?: null,
            $_POST['pagibig'] ?: null,
            $_POST['tin'] ?: null,
            $_POST['ewallet_provider'] ?: null,
            $_POST['ewallet_account'] ?: null,
            $_POST['ewallet_name'] ?: null
        ]);
        
        // Insert initial salary history
        $stmtHist = $pdo->prepare("INSERT INTO salary_history (employee_id, basic_salary, effective_date, reason) VALUES (?, ?, ?, ?)");
        $stmtHist->execute([$id, $_POST['basic_salary'] ?: 0, $_POST['hire_date'], 'Initial salary on hire']);
        
        auditLog('Employee Created', "Created employee {$code} - {$_POST['first_name']} {$_POST['last_name']}");
        $pdo->commit();
        header('Location: ' . BASE_URL . '/index.php?page=employees&success=created');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error creating employee: " . $e->getMessage();
        header('Location: ' . BASE_URL . '/index.php?page=employees&error=' . urlencode($error));
        exit;
    }
} elseif ($action === 'delete_employee') {
    try {
        $empId = $_POST['employee_id'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->execute([$empId]);
        auditLog('Employee Deleted', "Deleted employee ID {$empId}");
        header('Location: ' . BASE_URL . '/index.php?page=employees&success=deleted');
        exit;
    } catch (Exception $e) {
        $error = "Error deleting employee: " . $e->getMessage();
        header('Location: ' . BASE_URL . '/index.php?page=employees&error=' . urlencode($error));
        exit;
    }
}

header('Location: ' . BASE_URL . '/index.php?page=employees');
exit;
