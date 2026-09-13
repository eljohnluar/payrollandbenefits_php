<?php
require_once __DIR__ . '/../includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/index.php?page=login');
    exit;
}

verifyCsrf();
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (loginUser($email, $password)) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
} else {
    $error = 'Invalid email or password.';
    header('Location: ' . BASE_URL . '/index.php?page=login&error=' . urlencode($error) . '&email=' . urlencode($email));
    exit;
}
