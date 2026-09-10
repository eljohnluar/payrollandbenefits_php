<?php
require_once __DIR__ . '/claim_receipt_verification.php';
/**
 * Deterministic recipient matching for claim descriptions.  This is kept
 * server-side so the suggestion shown in the browser is the same one that is
 * recorded when the claim is submitted.
 */

function normalizeClaimText(string $value): string {
    $value = trim(preg_replace('/\s+/u', ' ', $value));
    $value = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $value);
    $value = trim(preg_replace('/\s+/u', ' ', $value));
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function employeeDisplayName(array $employee): string {
    return trim($employee['first_name'] . ' ' . (!empty($employee['middle_name']) ? $employee['middle_name'] . ' ' : '') . $employee['last_name'] . (!empty($employee['suffix']) ? ' ' . $employee['suffix'] : ''));
}

function claimTextTokens(string $text): array {
    $stopWords = ['about', 'after', 'along', 'also', 'and', 'are', 'been', 'business', 'claim', 'expense', 'for', 'from', 'have', 'into', 'more', 'our', 'receipt', 'that', 'the', 'their', 'this', 'team', 'than', 'with', 'was', 'were', 'will', 'your'];
    $tokens = preg_split('/[\s-]+/u', normalizeClaimText($text), -1, PREG_SPLIT_NO_EMPTY);
    return array_values(array_unique(array_filter($tokens, static fn($token) => (function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') : strlen($token)) >= 3 && !in_array($token, $stopWords, true))));
}

function ensureClaimRecipientSchema(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    ensureClaimReceiptSchema($pdo);

    $columns = $pdo->query('SHOW COLUMNS FROM claims')->fetchAll(PDO::FETCH_COLUMN);
    $additions = [
        'employee_id' => 'ALTER TABLE claims ADD COLUMN employee_id VARCHAR(20) NULL AFTER employee_name',
        'recipient_confidence' => 'ALTER TABLE claims ADD COLUMN recipient_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER ai_confidence',
        'recipient_method' => "ALTER TABLE claims ADD COLUMN recipient_method VARCHAR(100) NOT NULL DEFAULT 'Manual' AFTER recipient_confidence",
        'recipient_decision' => "ALTER TABLE claims ADD COLUMN recipient_decision ENUM('Accepted','Overridden','Rejected','Pending') NOT NULL DEFAULT 'Pending' AFTER recipient_method",
    ];
    foreach ($additions as $column => $sql) {
        if (!in_array($column, $columns, true)) $pdo->exec($sql);
    }
    $indexes = $pdo->query('SHOW INDEX FROM claims')->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('idx_claim_employee_date', $indexes, true)) {
        $pdo->exec('ALTER TABLE claims ADD INDEX idx_claim_employee_date (employee_id, claim_date)');
    }
    $foreignKeys = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'claims' AND COLUMN_NAME = 'employee_id' AND REFERENCED_TABLE_NAME = 'employees'")->fetchAll(PDO::FETCH_COLUMN);
    if (!$foreignKeys) {
        $pdo->exec('ALTER TABLE claims ADD CONSTRAINT fk_claim_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL');
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS ai_predictions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        claim_id INT NULL,
        description_normalized TEXT NOT NULL,
        suggested_employee_id VARCHAR(20) NULL,
        selected_employee_id VARCHAR(20) NULL,
        recipient_confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
        match_method VARCHAR(100) NOT NULL DEFAULT 'No match',
        recipient_decision ENUM('Accepted','Overridden','Rejected','Pending') NOT NULL DEFAULT 'Pending',
        candidate_snapshot JSON NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_prediction_selected (selected_employee_id),
        INDEX idx_prediction_suggested (suggested_employee_id),
        CONSTRAINT fk_prediction_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getClaimRecipientSuggestions(PDO $pdo, string $description, array $currentUser = []): array {
    $normalized = normalizeClaimText($description);
    if ((function_exists('mb_strlen') ? mb_strlen($normalized, 'UTF-8') : strlen($normalized)) < 3) return [];

    $employees = $pdo->query("SELECT id, code, first_name, middle_name, last_name, suffix, department, position, email FROM employees WHERE status IN ('Active','On Leave') ORDER BY first_name, last_name")->fetchAll();
    $scores = [];
    foreach ($employees as $employee) {
        $id = $employee['id'];
        $fullName = normalizeClaimText(employeeDisplayName($employee));
        $shortName = normalizeClaimText($employee['first_name'] . ' ' . $employee['last_name']);
        $first = normalizeClaimText($employee['first_name']);
        $last = normalizeClaimText($employee['last_name']);
        $method = '';
        $score = 0;
        if (str_contains($normalized, normalizeClaimText($employee['code']))) {
            $score = 99; $method = 'Employee code match';
        } elseif (str_contains($normalized, $fullName) || str_contains($normalized, $shortName)) {
            $score = 96; $method = 'Full name match';
        } elseif (mb_strlen($first, 'UTF-8') >= 3 && mb_strlen($last, 'UTF-8') >= 3 && str_contains($normalized, $first) && str_contains($normalized, $last)) {
            $score = 90; $method = 'Name entity match';
        } elseif (mb_strlen($last, 'UTF-8') >= 4 && str_contains($normalized, $last)) {
            $score = 76; $method = 'Surname match';
        } elseif (mb_strlen($first, 'UTF-8') >= 4 && str_contains($normalized, $first)) {
            $score = 66; $method = 'First-name match';
        }

        $department = normalizeClaimText($employee['department']);
        $position = normalizeClaimText($employee['position']);
        if ($department && str_contains($normalized, $department)) {
            $score = max($score, 72); $method = $method ?: 'Department match';
        }
        $positionWords = claimTextTokens($position);
        $positionMatches = array_filter($positionWords, static fn($word) => str_contains($normalized, $word));
        if (count($positionMatches) > 0) {
            $score = max($score, count($positionMatches) > 1 ? 72 : 62);
            $method = $method ?: 'Role match';
        }
        foreach (preg_split('/\s+/u', $department, -1, PREG_SPLIT_NO_EMPTY) as $departmentWord) {
            if (strlen($departmentWord) < 2) continue;
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($departmentWord, '/') . '(?![\p{L}\p{N}])/u', $normalized)) {
                $score = max($score, 70); $method = $method ?: 'Department keyword match'; break;
            }
        }
        if ($score > 0) $scores[$id] = ['employee' => $employee, 'score' => $score, 'method' => $method];
    }

    // Learn from accepted/overridden decisions: shared distinctive words add a
    // small signal but never outweigh an explicit name or employee code.
    $descriptionTokens = claimTextTokens($normalized);
    if ($descriptionTokens) {
        $learned = $pdo->query("SELECT selected_employee_id, description_normalized FROM ai_predictions WHERE selected_employee_id IS NOT NULL AND recipient_decision IN ('Accepted','Overridden') ORDER BY id DESC LIMIT 250")->fetchAll();
        foreach ($learned as $record) {
            $shared = count(array_intersect($descriptionTokens, claimTextTokens($record['description_normalized'])));
            if ($shared < 2) continue;
            $id = $record['selected_employee_id'];
            if (!isset($scores[$id])) {
                foreach ($employees as $employee) if ($employee['id'] === $id) $scores[$id] = ['employee' => $employee, 'score' => 0, 'method' => 'Learned history'];
            }
            if (isset($scores[$id])) {
                $scores[$id]['score'] = min(82, $scores[$id]['score'] + min(18, $shared * 6));
                if ($scores[$id]['method'] === '') $scores[$id]['method'] = 'Learned history';
            }
        }
    }

    // "my" maps to the signed-in employee only when their account email is an
    // employee email; HR accounts without a matching employee are not guessed.
    if (preg_match('/\b(my|myself)\b/u', $normalized) && !empty($currentUser['email'])) {
        foreach ($employees as $employee) {
            if (!empty($employee['email']) && strcasecmp($employee['email'], $currentUser['email']) === 0) {
                $scores[$employee['id']] = ['employee' => $employee, 'score' => 88, 'method' => 'Submitter context'];
            }
        }
    }

    uasort($scores, static fn($a, $b) => $b['score'] <=> $a['score']);
    $results = [];
    foreach (array_slice($scores, 0, 6) as $candidate) {
        $employee = $candidate['employee'];
        $results[] = [
            'id' => $employee['id'], 'name' => employeeDisplayName($employee), 'code' => $employee['code'],
            'department' => $employee['department'], 'position' => $employee['position'],
            'confidence' => (int) $candidate['score'], 'method' => $candidate['method'] ?: 'Possible match',
        ];
    }
    return $results;
}
