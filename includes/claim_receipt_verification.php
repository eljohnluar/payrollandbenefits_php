<?php
/**
 * Receipt verification for claims.
 *
 * The checks deliberately remain explainable: file safety, readability,
 * extracted receipt fields, amount/date consistency, and duplicate hashes.
 * If Tesseract is installed on the server it is used as an optional local OCR
 * engine; otherwise the claim stays in manual review when text cannot be read.
 */

function ensureClaimReceiptSchema(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $columns = $pdo->query('SHOW COLUMNS FROM claims')->fetchAll(PDO::FETCH_COLUMN);
    $additions = [
        'receipt_hash' => 'ALTER TABLE claims ADD COLUMN receipt_hash CHAR(64) NULL AFTER receipt_file',
        'receipt_mime' => 'ALTER TABLE claims ADD COLUMN receipt_mime VARCHAR(100) NULL AFTER receipt_hash',
        'receipt_verification_score' => 'ALTER TABLE claims ADD COLUMN receipt_verification_score TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER receipt_mime',
        'receipt_verification_status' => "ALTER TABLE claims ADD COLUMN receipt_verification_status VARCHAR(40) NOT NULL DEFAULT 'Not verified' AFTER receipt_verification_score",
        'receipt_verification_notes' => 'ALTER TABLE claims ADD COLUMN receipt_verification_notes TEXT NULL AFTER receipt_verification_status',
    ];
    foreach ($additions as $column => $sql) if (!in_array($column, $columns, true)) $pdo->exec($sql);
    $indexes = $pdo->query('SHOW INDEX FROM claims')->fetchAll(PDO::FETCH_COLUMN, 2);
    if (!in_array('idx_claim_receipt_hash', $indexes, true)) $pdo->exec('ALTER TABLE claims ADD INDEX idx_claim_receipt_hash (receipt_hash)');

    $pdo->exec("CREATE TABLE IF NOT EXISTS claim_receipt_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        claim_id INT NOT NULL,
        image_readable TINYINT(1) NOT NULL DEFAULT 0,
        ocr_available TINYINT(1) NOT NULL DEFAULT 0,
        extracted_merchant VARCHAR(255) DEFAULT NULL,
        extracted_receipt_date DATE DEFAULT NULL,
        extracted_amount DECIMAL(12,2) DEFAULT NULL,
        extracted_or_number VARCHAR(100) DEFAULT NULL,
        extracted_tin VARCHAR(100) DEFAULT NULL,
        extracted_text TEXT DEFAULT NULL,
        amount_matches TINYINT(1) NOT NULL DEFAULT 0,
        date_within_period TINYINT(1) NOT NULL DEFAULT 0,
        duplicate_claim_id INT DEFAULT NULL,
        verification_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
        issues JSON NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_receipt_verification_claim (claim_id),
        CONSTRAINT fk_receipt_verification_claim FOREIGN KEY (claim_id) REFERENCES claims(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function receiptOcrIsAvailable(): bool {
    static $available = null;
    if ($available !== null) return $available;
    if (!function_exists('shell_exec')) return $available = false;
    $probe = PHP_OS_FAMILY === 'Windows' ? 'where tesseract 2>NUL' : 'command -v tesseract 2>/dev/null';
    return $available = trim((string) shell_exec($probe)) !== '';
}

function extractReceiptText(string $path, string $mime): string {
    if (receiptOcrIsAvailable()) {
        $command = 'tesseract ' . escapeshellarg($path) . ' stdout --psm 6 2>&1';
        $text = trim((string) shell_exec($command));
        if ($text !== '') return mb_substr($text, 0, 20000, 'UTF-8');
    }
    // Text-based PDFs may contain searchable receipt text even without OCR.
    if ($mime === 'application/pdf') {
        $raw = (string) @file_get_contents($path, false, null, 0, 1000000);
        preg_match_all('/\(([^()]{3,300})\)/', $raw, $matches);
        return mb_substr(trim(implode("\n", $matches[1] ?? [])), 0, 20000, 'UTF-8');
    }
    return '';
}

function parseReceiptFields(string $text): array {
    $compact = preg_replace('/\s+/', ' ', $text);
    $amount = null;
    if (preg_match('/(?:grand\s*total|total(?:\s*due)?|amount\s*due|net\s*amount)\D{0,12}([₱P]?\s*[0-9][0-9,]*(?:\.\d{2})?)/iu', $compact, $match)) {
        $amount = (float) str_replace([',', '₱', 'P', ' '], '', $match[1]);
    }
    $date = null;
    if (preg_match('/\b(20\d{2}[-\/.](?:0[1-9]|1[0-2])[-\/.](?:0[1-9]|[12]\d|3[01]))\b/', $compact, $match)) $date = str_replace(['/', '.'], '-', $match[1]);
    elseif (preg_match('/\b((?:0?[1-9]|1[0-2])[-\/](?:0?[1-9]|[12]\d|3[01])[-\/](?:20)?\d{2})\b/', $compact, $match)) {
        $parsed = strtotime($match[1]);
        if ($parsed) $date = date('Y-m-d', $parsed);
    }
    $merchant = null;
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if (mb_strlen($line, 'UTF-8') >= 3 && !preg_match('/(?:receipt|official|invoice|tin|date|total|cashier)/iu', $line) && preg_match('/[A-Za-z]/', $line)) { $merchant = mb_substr($line, 0, 255, 'UTF-8'); break; }
    }
    preg_match('/(?:OR\s*(?:No\.?|#)?|official\s*receipt\s*(?:no\.?|#)?)\s*[:#-]?\s*([A-Z0-9-]{4,100})/iu', $compact, $or);
    preg_match('/\bTIN\s*[:#-]?\s*([0-9-]{9,20})\b/iu', $compact, $tin);
    return ['merchant' => $merchant, 'date' => $date, 'amount' => $amount, 'or_number' => $or[1] ?? null, 'tin' => $tin[1] ?? null];
}

function validateAndStoreReceipt(array $upload): array {
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('A readable receipt image or PDF is required.');
    if (($upload['size'] ?? 0) < 512 || $upload['size'] > 10 * 1024 * 1024) throw new RuntimeException('Receipt files must be between 512 bytes and 10 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Use a JPG, PNG, WEBP, or PDF receipt.');
    if (str_starts_with($mime, 'image/') && @getimagesize($upload['tmp_name']) === false) throw new RuntimeException('The receipt image is invalid or unreadable.');
    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'receipts';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Receipt storage is unavailable.');
    $name = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    $path = $directory . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($upload['tmp_name'], $path)) throw new RuntimeException('The receipt could not be uploaded.');
    return ['file' => $name, 'path' => $path, 'mime' => $mime, 'hash' => hash_file('sha256', $path)];
}

function analyzeReceipt(PDO $pdo, array $receipt, float $claimAmount, string $claimDate, string $employeeName, string $category): array {
    $issues = [];
    $readable = true;
    if (str_starts_with($receipt['mime'], 'image/')) {
        $size = @getimagesize($receipt['path']);
        $readable = $size && $size[0] >= 500 && $size[1] >= 300;
        if (!$readable) $issues[] = 'Image resolution is too low for reliable verification.';
    }
    $text = extractReceiptText($receipt['path'], $receipt['mime']);
    $fields = parseReceiptFields($text);
    $duplicate = $pdo->prepare('SELECT id, claim_number FROM claims WHERE receipt_hash = ? LIMIT 1');
    $duplicate->execute([$receipt['hash']]);
    $duplicateClaim = $duplicate->fetch();
    if ($duplicateClaim) $issues[] = 'Duplicate receipt detected: already used by ' . $duplicateClaim['claim_number'] . '.';
    if ($text === '') $issues[] = receiptOcrIsAvailable() ? 'No usable text was detected on the receipt.' : 'OCR is not installed on this server; receipt text requires manual review.';
    if (!$fields['merchant'] && $text !== '') $issues[] = 'Merchant name could not be extracted.';
    $amountMatches = $fields['amount'] !== null && abs($fields['amount'] - $claimAmount) <= max(1, $claimAmount * .01);
    if ($fields['amount'] !== null && !$amountMatches) $issues[] = 'Receipt total does not match the claimed amount.';
    $dateWithinPeriod = false;
    if ($fields['date']) {
        $dateWithinPeriod = abs((strtotime($claimDate) - strtotime($fields['date'])) / 86400) <= 30;
        if (!$dateWithinPeriod) $issues[] = 'Receipt date is outside the 30-day claim period.';
    }
    $nameTokens = array_filter(preg_split('/\s+/', mb_strtolower($employeeName, 'UTF-8')), static fn($part) => mb_strlen($part, 'UTF-8') >= 4);
    $nameMatches = $text !== '' && count(array_filter($nameTokens, static fn($part) => str_contains(mb_strtolower($text, 'UTF-8'), $part))) > 0;
    $score = $readable ? 45 : 10;
    if ($text !== '') $score += 10;
    if ($fields['merchant']) $score += 6;
    if ($amountMatches) $score += 12;
    if ($dateWithinPeriod) $score += 10;
    if ($fields['or_number']) $score += 4;
    if ($fields['tin']) $score += 4;
    if ($nameMatches) $score += 4;
    if ($duplicateClaim) $score -= 55;
    $score = max(0, min(100, $score));
    $status = $score >= 90 ? 'Highly likely legitimate' : ($score >= 70 ? 'Likely legitimate — note' : ($score >= 50 ? 'Manual review required' : ($score >= 30 ? 'Suspicious — manual review' : 'Likely fraudulent')));
    return ['score' => $score, 'status' => $status, 'issues' => $issues, 'readable' => $readable, 'ocr_available' => receiptOcrIsAvailable(), 'text' => $text, 'fields' => $fields, 'amount_matches' => $amountMatches, 'date_within_period' => $dateWithinPeriod, 'duplicate_claim_id' => $duplicateClaim['id'] ?? null];
}

function saveReceiptVerification(PDO $pdo, int $claimId, array $analysis): void {
    $fields = $analysis['fields'];
    $statement = $pdo->prepare('INSERT INTO claim_receipt_verifications (claim_id, image_readable, ocr_available, extracted_merchant, extracted_receipt_date, extracted_amount, extracted_or_number, extracted_tin, extracted_text, amount_matches, date_within_period, duplicate_claim_id, verification_score, issues) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $statement->execute([$claimId, (int) $analysis['readable'], (int) $analysis['ocr_available'], $fields['merchant'], $fields['date'], $fields['amount'], $fields['or_number'], $fields['tin'], $analysis['text'] ?: null, (int) $analysis['amount_matches'], (int) $analysis['date_within_period'], $analysis['duplicate_claim_id'], $analysis['score'], json_encode($analysis['issues'], JSON_UNESCAPED_UNICODE)]);
}
