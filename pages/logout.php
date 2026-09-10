<?php
require_once __DIR__ . '/../includes/config.php';
logoutUser();
header('Location: ' . BASE_URL . '/index.php?page=login');
exit;
