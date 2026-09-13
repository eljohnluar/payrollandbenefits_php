<?php
require_once __DIR__ . '/../includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=benefit_plans');
    exit;
}

verifyCsrf();
$action = $_POST['action'] ?? '';
$pdo = getDB();

if ($action === 'save_plan') {
    try {
        $stmt = $pdo->prepare("UPDATE benefit_plans SET monthly_premium=?, employer_share=? WHERE id=?");
        $stmt->execute([$_POST['monthly_premium'], $_POST['employer_share'], $_POST['plan_id']]);
        auditLog('Benefit Plan Updated', "Updated plan ID {$_POST['plan_id']}");
        header('Location: ' . BASE_URL . '/index.php?page=benefit_plans&msg=' . urlencode('Plan updated.'));
        exit;
    } catch (Exception $e) {
        $error = "Error updating plan: " . $e->getMessage();
        header('Location: ' . BASE_URL . '/index.php?page=benefit_plans&error=' . urlencode($error));
        exit;
    }
}

header('Location: ' . BASE_URL . '/index.php?page=benefit_plans');
exit;
