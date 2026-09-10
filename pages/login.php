<?php
require_once __DIR__ . '/../includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (loginUser($email, $password)) {
        header('Location: ' . BASE_URL . '/index.php?page=dashboard');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <svg width="22" height="22" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
        <line x1="1" y1="10" x2="23" y2="10"/>
      </svg>
    </div>
    <h1>Payroll & Benefits</h1>
    <div class="login-sub">System for <?= COMPANY_NAME ?></div>
    
    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      
      <div class="form-group">
        <label class="form-label required" for="login-username">Username</label>
        <input type="text" id="login-username" name="email" class="form-control" placeholder="Enter your username" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
      </div>
      
      <div class="form-group mb-4">
        <label class="form-label required" for="login-password">Password</label>
        <input type="password" id="login-password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      
      <button type="submit" id="login-submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
    </form>
    
    <div style="text-align:center; margin-top: 20px; font-size: 13px; color: var(--text-muted);">
      Don't have an account? <a href="<?= BASE_URL ?>/index.php?page=register" style="color: var(--primary); font-weight: 600;">Register here</a>
    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
