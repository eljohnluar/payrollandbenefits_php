<?php
// =====================================================
// CONFIG & HELPERS
// =====================================================
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'payroll_benefits_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME', 'Payroll & Benefits');
define('COMPANY_NAME', 'TRI-M GLOBAL LOGISTICS & TRADING INC.');
define('BASE_URL', '/payrollandbenefits_php');

// ── PDO connection ──────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;padding:20px;background:#fee2e2;color:#991b1b;border-radius:8px;margin:20px;">
                <strong>Database Connection Error:</strong><br>' . htmlspecialchars($e->getMessage()) . '<br><br>
                Make sure XAMPP MySQL is running and the database <strong>' . DB_NAME . '</strong> exists.<br>
                Import <code>schema.sql</code> first.
            </div>');
        }
    }
    return $pdo;
}

// ── Auth helpers ───────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php?page=login');
        exit;
    }
}

function getCurrentUser(): array {
    return $_SESSION['user'] ?? [];
}

function loginUser(string $email, string $password): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'email'    => $user['email'],
            'name'     => $user['name'],
            'role'     => $user['role'],
            'initials' => $user['initials'],
        ];
        return true;
    }
    // Demo login fallback (plain-text password for dev)
    if ($user && $password === 'password123') {
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'email'    => $user['email'],
            'name'     => $user['name'],
            'role'     => $user['role'],
            'initials' => $user['initials'],
        ];
        return true;
    }
    return false;
}

function logoutUser(): void {
    $_SESSION = [];
    session_destroy();
}

// ── Formatting helpers ─────────────────────────────
function formatCurrency(float $amount): string {
    return '₱' . number_format($amount, 2);
}

function formatDate(?string $date): string {
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date('M j, Y', $ts) : '';
}

function getStatusBadgeClass(string $status): string {
    return match($status) {
        'Active', 'Paid', 'Approved', 'Released'                         => 'badge-success',
        'Pending', 'Processing', 'AI Review', 'Pending Approval','On Leave','Draft' => 'badge-warning',
        'Enrolled'                                                         => 'badge-info',
        'Regular'                                                          => 'badge-muted',
        'Rejected', 'Resigned', 'Terminated', 'Cancelled'                => 'badge-danger',
        default                                                            => 'badge-muted',
    };
}

// ── Audit logger ───────────────────────────────────
function auditLog(string $action, string $details = ''): void {
    try {
        $pdo  = getDB();
        $user = getCurrentUser();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log (user_id, user_name, action, details, ip_address) VALUES (?,?,?,?,?)'
        );
        $stmt->execute([
            $user['id']   ?? null,
            $user['name'] ?? null,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Throwable $e) { /* silent */ }
}

// ── Payroll computation helpers ────────────────────
function computeSSS(float $salary): array {
    if ($salary < 5000)  return ['ee' => 225,  'er' => 475];
    if ($salary < 10000) return ['ee' => 450,  'er' => 950];
    if ($salary < 15000) return ['ee' => 675,  'er' => 1425];
    if ($salary < 20000) return ['ee' => 900,  'er' => 1900];
    return ['ee' => 900, 'er' => min($salary * 0.095, 1900)];
}

function computePhilHealth(float $salary): array {
    $base = min($salary, 100000);
    $ee   = $base * 0.02;
    return ['ee' => $ee, 'er' => $ee];
}

function computePagIBIG(float $salary): array {
    $ee = $salary >= 5000 ? 100 : 50;
    return ['ee' => $ee, 'er' => 100];
}

function computeWithholdingTax(float $grossPay, float $totalContribEE): float {
    $taxable  = $grossPay - $totalContribEE;
    $annual   = $taxable * 12;
    $annualTax = 0;
    if ($annual <= 250000)        $annualTax = 0;
    elseif ($annual <= 400000)    $annualTax = ($annual - 250000) * 0.15;
    elseif ($annual <= 800000)    $annualTax = 22500  + ($annual - 400000) * 0.20;
    elseif ($annual <= 2000000)   $annualTax = 102500 + ($annual - 800000) * 0.25;
    elseif ($annual <= 8000000)   $annualTax = 402500 + ($annual - 2000000) * 0.30;
    else                          $annualTax = 2202500 + ($annual - 8000000) * 0.35;
    return round($annualTax / 12, 2);
}

// ── Current page helper ────────────────────────────
function currentPage(): string {
    return $_GET['page'] ?? 'dashboard';
}

// ── CSRF helpers ───────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}
