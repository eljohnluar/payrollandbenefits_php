<?php
require_once __DIR__ . '/../includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=register');
    exit;
}

verifyCsrf();
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    $error = 'Username and password are required.';
    header('Location: ' . BASE_URL . '/index.php?page=register&error=' . urlencode($error));
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$username]);
if ($stmt->fetch()) {
    $error = 'Username is already taken.';
    header('Location: ' . BASE_URL . '/index.php?page=register&error=' . urlencode($error));
    exit;
}

$hashed = password_hash($password, PASSWORD_DEFAULT);
$initials = strtoupper(substr($username, 0, 2));

$insert = $pdo->prepare("INSERT INTO users (email, password, name, role, initials, is_active) VALUES (?, ?, ?, 'HR', ?, 1)");
if ($insert->execute([$username, $hashed, $username, $initials])) {
    $success = 'Registration successful. You can now login.';
    header('Location: ' . BASE_URL . '/index.php?page=register&success=' . urlencode($success));
    exit;
} else {
    $error = 'An error occurred during registration.';
    header('Location: ' . BASE_URL . '/index.php?page=register&error=' . urlencode($error));
    exit;
}
