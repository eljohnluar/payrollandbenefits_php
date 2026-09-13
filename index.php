<?php
// Front Controller – routes ?page= to page files
require_once __DIR__ . '/includes/config.php';

$page = preg_replace('/[^a-z0-9_]/', '', strtolower($_GET['page'] ?? 'dashboard'));

// Public pages (no auth required)
$publicPages = ['login', 'register'];

if (!in_array($page, $publicPages)) {
    requireLogin();
}

// Route POST requests to api/ if endpoint exists
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiFile = __DIR__ . '/api/' . $page . '.php';
    if (file_exists($apiFile)) {
        require $apiFile;
        exit;
    }
}

$pageFile = __DIR__ . '/pages/' . $page . '.php';

if (!file_exists($pageFile)) {
    $page     = 'dashboard';
    $pageFile = __DIR__ . '/pages/dashboard.php';
}

include $pageFile;
