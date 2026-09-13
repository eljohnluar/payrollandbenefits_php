<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=compensation');
    exit;
}

verifyCsrf();
$action = $_POST['action'] ?? '';
$empId = $_POST['emp_id'] ?? ($_GET['emp_id'] ?? '');
$pdo = getDB();
$success = '';

if ($empId) {
    try {
        if ($action === 'update_salary') {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE employees SET basic_salary = ? WHERE id = ?");
            $stmt->execute([$_POST['basic_salary'], $empId]);
            
            $stmtHist = $pdo->prepare("INSERT INTO salary_history (employee_id, basic_salary, effective_date, reason) VALUES (?, ?, ?, ?)");
            $stmtHist->execute([$empId, $_POST['basic_salary'], $_POST['effective_date'], $_POST['reason']]);
            
            auditLog('Salary Updated', "Updated salary for emp {$empId}");
            $pdo->commit();
            $success = 'Salary updated successfully.';
        } elseif ($action === 'add_allowance') {
            $stmt = $pdo->prepare("INSERT INTO allowances (employee_id, type, amount, frequency, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$empId, $_POST['type'], $_POST['amount'], $_POST['frequency'], isset($_POST['is_active']) ? 1 : 0]);
            $success = 'Allowance added.';
        } elseif ($action === 'edit_allowance') {
            $stmt = $pdo->prepare("UPDATE allowances SET type=?, amount=?, frequency=?, is_active=? WHERE id=? AND employee_id=?");
            $stmt->execute([$_POST['type'], $_POST['amount'], $_POST['frequency'], isset($_POST['is_active']) ? 1 : 0, $_POST['allowance_id'], $empId]);
            $success = 'Allowance updated.';
        } elseif ($action === 'delete_allowance') {
            $stmt = $pdo->prepare("DELETE FROM allowances WHERE id=? AND employee_id=?");
            $stmt->execute([$_POST['allowance_id'], $empId]);
            $success = 'Allowance deleted.';
        } elseif ($action === 'add_loan') {
            $stmt = $pdo->prepare("INSERT INTO loans (employee_id, type, total_amount, monthly_deduction, remaining_balance, start_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$empId, $_POST['type'], $_POST['total_amount'], $_POST['monthly_deduction'], $_POST['total_amount'], $_POST['start_date']]);
            $success = 'Loan added.';
        } elseif ($action === 'delete_loan') {
            $stmt = $pdo->prepare("DELETE FROM loans WHERE id=? AND employee_id=?");
            $stmt->execute([$_POST['loan_id'], $empId]);
            $success = 'Loan deleted.';
        }
        
        header('Location: ' . BASE_URL . "/index.php?page=compensation&emp_id={$empId}&msg=" . urlencode($success));
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
        header('Location: ' . BASE_URL . "/index.php?page=compensation&emp_id={$empId}&error=" . urlencode($error));
        exit;
    }
}

header('Location: ' . BASE_URL . '/index.php?page=compensation');
exit;
