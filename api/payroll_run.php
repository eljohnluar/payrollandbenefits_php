<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=payroll_run');
    exit;
}

verifyCsrf();
$pdo = getDB();
$runId = $_POST['run_id'] ?? ($_GET['run_id'] ?? null);
$action = $_POST['action'] ?? '';

if (!$runId) {
    header('Location: ' . BASE_URL . '/index.php?page=payroll_run');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM payroll_runs WHERE id = ?");
$stmt->execute([$runId]);
$runDetails = $stmt->fetch();

if (!$runDetails) {
    header('Location: ' . BASE_URL . '/index.php?page=payroll_run');
    exit;
}

try {
    if ($action === 'toggle_include') {
        $stmt = $pdo->prepare("UPDATE payroll_items SET is_included = ? WHERE id = ? AND payroll_run_id = ?");
        $stmt->execute([$_POST['is_included'] ? 1 : 0, $_POST['item_id'], $runId]);
        // Recompute totals silently
        $pdo->query("UPDATE payroll_runs r SET 
            total_gross = (SELECT SUM(gross_pay) FROM payroll_items WHERE payroll_run_id=r.id AND is_included=1),
            total_deductions = (SELECT SUM(total_deductions) FROM payroll_items WHERE payroll_run_id=r.id AND is_included=1),
            total_net = (SELECT SUM(net_pay) FROM payroll_items WHERE payroll_run_id=r.id AND is_included=1)
            WHERE id = $runId");
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'recompute' || $action === 'run_payroll') {
        $stmtI = $pdo->prepare("SELECT * FROM payroll_items WHERE payroll_run_id = ? ORDER BY department, employee_name");
        $stmtI->execute([$runId]);
        $items = $stmtI->fetchAll();

        $pdo->beginTransaction();
        foreach ($items as $item) {
            if (!$item['is_included']) continue;
            $empId = $item['employee_id'];
            
            // 1. Fetch attendance
            $stmtAtt = $pdo->prepare("SELECT SUM(CASE WHEN status='P' THEN 1 WHEN status='OT' THEN 1 WHEN status='H' THEN 0.5 ELSE 0 END) as days, SUM(ot_hours) as ot FROM attendance_logs WHERE employee_id=? AND log_date BETWEEN ? AND ?");
            $stmtAtt->execute([$empId, $runDetails['period_start'], $runDetails['period_end']]);
            $att = $stmtAtt->fetch();
            $daysWorked = $att['days'] ?: 22;
            $otHours = $att['ot'] ?: 0;
            
            // 2. Fetch basic salary
            $sal = $pdo->prepare("SELECT basic_salary FROM employees WHERE id=?");
            $sal->execute([$empId]);
            $basicSalary = $sal->fetchColumn() ?: 0;
            
            $basicPay = ($basicSalary / 22) * $daysWorked;
            $overtimePay = ($basicSalary / 22 / 8) * 1.25 * $otHours;
            
            // 3. Allowances
            $stmtAllow = $pdo->prepare("SELECT SUM(amount) FROM allowances WHERE employee_id=? AND is_active=1 AND frequency='Monthly'");
            $stmtAllow->execute([$empId]);
            $allowances = $stmtAllow->fetchColumn() ?: 0;
            
            // 4. Claims
            $stmtClaims = $pdo->prepare("SELECT SUM(amount) FROM claims WHERE (employee_id = ? OR (employee_id IS NULL AND employee_name = ?)) AND claim_date BETWEEN ? AND ? AND status='Approved'");
            $stmtClaims->execute([$empId, $item['employee_name'], $runDetails['period_start'], $runDetails['period_end']]);
            $approvedClaims = $stmtClaims->fetchColumn() ?: 0;
            
            $grossPay = $basicPay + $overtimePay + $allowances + $approvedClaims;
            
            // 5. Taxes & Contrib
            $sss = computeSSS($basicSalary);
            $ph = computePhilHealth($basicSalary);
            $pagibig = computePagIBIG($basicSalary);
            $totalContribEE = $sss['ee'] + $ph['ee'] + $pagibig['ee'];
            $tax = computeWithholdingTax($grossPay, $totalContribEE);
            
            // 6. Loans
            $stmtLoans = $pdo->prepare("SELECT SUM(monthly_deduction) FROM loans WHERE employee_id=?");
            $stmtLoans->execute([$empId]);
            $loans = $stmtLoans->fetchColumn() ?: 0;
            
            $totalDed = $totalContribEE + $tax + $loans;
            $netPay = $grossPay - $totalDed;
            
            $newStatus = ($action === 'run_payroll') ? 'Approved' : 'Draft';
            
            $updItem = $pdo->prepare("UPDATE payroll_items SET 
                days_worked=?, ot_hours=?, basic_pay=?, overtime_pay=?, allowances=?, claims_amount=?, gross_pay=?,
                sss_ee=?, sss_er=?, philhealth_ee=?, philhealth_er=?, pagibig_ee=?, pagibig_er=?, withholding_tax=?,
                loans_deduction=?, total_deductions=?, net_pay=?, status=? WHERE id=?");
            $updItem->execute([
                $daysWorked, $otHours, $basicPay, $overtimePay, $allowances, $approvedClaims, $grossPay,
                $sss['ee'], $sss['er'], $ph['ee'], $ph['er'], $pagibig['ee'], $pagibig['er'], $tax,
                $loans, $totalDed, $netPay, $newStatus, $item['id']
            ]);
        }
        
        $runStatus = ($action === 'run_payroll') ? 'Approved' : 'Draft';
        $pdo->query("UPDATE payroll_runs r SET 
            total_gross = (SELECT SUM(gross_pay) FROM payroll_items WHERE payroll_run_id=r.id AND is_included=1),
            total_deductions = (SELECT SUM(total_deductions) FROM payroll_items WHERE payroll_run_id=r.id AND is_included=1),
            total_net = (SELECT SUM(net_pay) FROM payroll_items WHERE payroll_run_id=r.id AND is_included=1),
            status = '$runStatus'
            WHERE id = $runId");
            
        $pdo->commit();
        $msg = ($action === 'run_payroll') ? "Payroll Run Approved successfully." : "Payroll Recomputed successfully.";
        header("Location: " . BASE_URL . "/index.php?page=payroll_run&run_id={$runId}&msg=" . urlencode($msg));
        exit;
    } elseif ($action === 'mark_paid') {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE payroll_items SET status='Paid', pay_date=CURRENT_DATE WHERE payroll_run_id=? AND is_included=1")->execute([$runId]);
        $pdo->prepare("UPDATE payroll_runs SET status='Paid', run_date=NOW() WHERE id=?")->execute([$runId]);
        $pdo->commit();
        header("Location: " . BASE_URL . "/index.php?page=payroll_run&run_id={$runId}&msg=" . urlencode("Payroll Marked as Paid."));
        exit;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $error = "Error: " . $e->getMessage();
    header("Location: " . BASE_URL . "/index.php?page=payroll_run&run_id={$runId}&error=" . urlencode($error));
    exit;
}

header("Location: " . BASE_URL . "/index.php?page=payroll_run&run_id={$runId}");
exit;
