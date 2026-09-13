<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=attendance');
    exit;
}

verifyCsrf();
$action = $_POST['action'] ?? '';
$selectedDate = $_POST['selected_date'] ?? ($_GET['selected_date'] ?? date('Y-m-d'));
$pdo = getDB();
$msg = '';

try {
    if ($action === 'save_attendance') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO attendance_logs (employee_id, log_date, status, ot_hours, notes) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), ot_hours=VALUES(ot_hours), notes=VALUES(notes)");
        
        $employees = $_POST['emp'] ?? [];
        foreach($employees as $empId => $data) {
            if (!empty($data['status'])) {
                $ot = ($data['status'] === 'OT') ? (float)$data['ot_hours'] : 0;
                $stmt->execute([$empId, $selectedDate, $data['status'], $ot, $data['notes']]);
            }
        }
        $pdo->commit();
        $msg = "Attendance saved successfully.";
    } elseif ($action === 'mark_all_present') {
        $pdo->beginTransaction();
        $emps = $pdo->query("SELECT id FROM employees WHERE status IN ('Active', 'On Leave')")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("INSERT INTO attendance_logs (employee_id, log_date, status, ot_hours, notes) VALUES (?, ?, 'P', 0, '') ON DUPLICATE KEY UPDATE status='P', ot_hours=0");
        foreach($emps as $empId) {
            $stmt->execute([$empId, $selectedDate]);
        }
        $pdo->commit();
        $msg = "All marked present.";
    } elseif ($action === 'clear_attendance') {
        $stmt = $pdo->prepare("DELETE FROM attendance_logs WHERE log_date = ?");
        $stmt->execute([$selectedDate]);
        $msg = "Attendance cleared for date.";
    }
    
    header("Location: " . BASE_URL . "/index.php?page=attendance&selected_date={$selectedDate}&msg=" . urlencode($msg));
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = "Error: " . $e->getMessage();
    header("Location: " . BASE_URL . "/index.php?page=attendance&selected_date={$selectedDate}&error=" . urlencode($error));
    exit;
}
