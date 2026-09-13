<?php
require_once __DIR__ . '/../includes/config.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php?page=dashboard');
    exit;
}

$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register – <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <svg width="22" height="22" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
        <circle cx="12" cy="7" r="4"></circle>
      </svg>
    </div>
    <h1>Register HR User</h1>
    <div class="login-sub">Create a new account</div>
    
    <?php if ($error): ?>
      <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div style="background:var(--success-bg); color:var(--success); padding:10px 12px; border-radius:var(--radius); font-size:13px; margin-bottom:14px; text-align:center;">
        <?= htmlspecialchars($success) ?><br>
        <a href="<?= BASE_URL ?>/index.php?page=login" style="font-weight:bold; color:var(--success); text-decoration:underline;">Click here to sign in</a>
      </div>
    <?php else: ?>
        <form method="POST" action="<?= BASE_URL ?>/api/register.php">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          
          <div class="form-group">
            <label class="form-label required" for="reg-username">Username</label>
            <input type="text" id="reg-username" name="username" class="form-control" placeholder="Choose a username" required>
          </div>
          
          <div class="form-group mb-4">
            <label class="form-label required" for="reg-password">Password</label>
            <input type="password" id="reg-password" name="password" class="form-control" placeholder="••••••••" required>
          </div>
          
          <button type="submit" class="btn btn-primary btn-block btn-lg">Create Account</button>
        </form>
        
        <div style="text-align:center; margin-top: 20px; font-size: 13px; color: var(--text-muted);">
          Already have an account? <a href="<?= BASE_URL ?>/index.php?page=login" style="color: var(--primary); font-weight: 600;">Sign in here</a>
        </div>
    <?php endif; ?>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
