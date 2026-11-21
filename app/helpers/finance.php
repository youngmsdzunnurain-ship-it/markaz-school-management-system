<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../middleware/auth.php';

function financeRequireAccess(): void
{
    $role = currentAdminRole();
    if ($role === 'admin') return;
    if ($role === 'management') {
        requireManagementPermission('school_fees');
        return;
    }
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Finance access is restricted to Admin and specifically authorised Management accounts.']);
    exit;
}

function financeJson(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function financeInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) financeJson(['success' => false, 'message' => 'Send a valid JSON request.'], 400);
    return $data;
}

function financeRequireSafePost(): void
{
    if (($_SERVER['HTTP_X_MARKAZ_REQUEST'] ?? '') !== 'finance') {
        financeJson(['success' => false, 'message' => 'Reload the Finance page and try again.'], 403);
    }
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $requestHost = preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (!is_string($originHost) || strcasecmp($originHost, $requestHost) !== 0) {
            financeJson(['success' => false, 'message' => 'Cross-origin finance requests are not allowed.'], 403);
        }
    }
}

function financeToCents(string $value): int
{
    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
}

function financeFromCents(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function financeMoney(mixed $value, bool $allowZero = false): string
{
    $value = trim((string) $value);
    if (!preg_match('/^(?:0|[1-9][0-9]{0,11})(?:\\.[0-9]{1,2})?$/', $value)) {
        financeJson(['success' => false, 'message' => 'Enter a valid amount with up to two decimal places.'], 422);
    }
    if (!$allowZero && (float) $value <= 0) financeJson(['success' => false, 'message' => 'Amount must be greater than zero.'], 422);
    return financeFromCents(financeToCents($value));
}

function financeAudit(PDO $pdo, string $action, string $entity, ?int $entityId, array $details = []): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO finance_audit_logs (actor_id, action, entity_type, entity_id, details_json, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        (int) ($_SESSION['admin_id'] ?? 0),
        $action,
        $entity,
        $entityId,
        json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null
    ]);
}

function financeValidDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
}

function financeProrateMonthlySalary(string $monthlySalary, int $coveredDays, int $daysInMonth): string
{
    if ($daysInMonth < 1 || $coveredDays < 0 || $coveredDays > $daysInMonth) {
        throw new InvalidArgumentException('Invalid salary proration day count.');
    }
    $numerator = financeToCents($monthlySalary) * $coveredDays;
    return financeFromCents(intdiv($numerator + intdiv($daysInMonth, 2), $daysInMonth));
}

function financeCalculateLateMinutes(string $expectedArrival, string $actualArrival): int
{
    $expected = DateTimeImmutable::createFromFormat('!H:i:s', strlen($expectedArrival) === 5 ? $expectedArrival . ':00' : $expectedArrival);
    $actual = DateTimeImmutable::createFromFormat('!H:i:s', strlen($actualArrival) === 5 ? $actualArrival . ':00' : $actualArrival);
    if (!$expected || !$actual) throw new InvalidArgumentException('Arrival times must use HH:MM or HH:MM:SS.');
    return (int) floor(max(0, $actual->getTimestamp() - $expected->getTimestamp()) / 60);
}

function financeNextNumber(string $prefix): string
{
    return $prefix . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function financePostJournal(PDO $pdo, string $sourceType, ?int $sourceId, string $memo, string $date, array $lines): int
{
    $debit = 0;
    $credit = 0;
    foreach ($lines as $line) {
        $debit += financeToCents((string) $line['debit']);
        $credit += financeToCents((string) $line['credit']);
    }
    if ($debit !== $credit || $debit <= 0) {
        throw new DomainException('The accounting journal is not balanced.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO finance_journals (journal_number, transaction_date, source_type, source_id, memo, status, created_by, approved_by, posted_at)
         VALUES (?, ?, ?, ?, ?, "posted", ?, ?, NOW())'
    );
    $actor = (int) ($_SESSION['admin_id'] ?? 0);
    $stmt->execute([financeNextNumber('JRN'), $date, $sourceType, $sourceId, $memo, $actor, $actor]);
    $journalId = (int) $pdo->lastInsertId();

    $lineStmt = $pdo->prepare(
        'INSERT INTO finance_journal_lines (journal_id, account_id, description, debit, credit)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($lines as $line) {
        $lineStmt->execute([$journalId, (int) $line['account_id'], $line['description'] ?? null, $line['debit'], $line['credit']]);
    }
    return $journalId;
}

function financeAccountId(PDO $pdo, string $code): int
{
    $stmt = $pdo->prepare('SELECT id FROM finance_accounts WHERE account_code = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$code]);
    $id = (int) $stmt->fetchColumn();
    if ($id < 1) throw new RuntimeException('Required finance account is not configured: ' . $code);
    return $id;
}
