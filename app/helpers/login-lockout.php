<?php
declare(strict_types=1);

function markazEnsureLoginLockoutSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_lockouts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        role VARCHAR(40) NOT NULL,
        identifier VARCHAR(190) NOT NULL,
        failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
        locked_until DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_login_lockout_role_identifier (role, identifier),
        KEY idx_login_lockout_expiry (locked_until)
    ) ENGINE=InnoDB");
}

function markazLoginLockoutEnabled(PDO $pdo): bool
{
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'login_lockout_enabled' LIMIT 1");
    $stmt->execute();
    $value = $stmt->fetchColumn();
    return $value === false || in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function markazGetActiveLoginLockout(PDO $pdo, string $role, string $identifier): ?array
{
    $reset = $pdo->prepare('UPDATE login_lockouts SET failed_attempts = 0, locked_until = NULL WHERE role = ? AND identifier = ? AND locked_until IS NOT NULL AND locked_until <= NOW()');
    $reset->execute([$role, $identifier]);
    $stmt = $pdo->prepare('SELECT failed_attempts, locked_until, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS remaining_seconds FROM login_lockouts WHERE role = ? AND identifier = ? LIMIT 1');
    $stmt->execute([$role, $identifier]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || empty($row['locked_until']) || (int) $row['remaining_seconds'] < 1) return null;
    return ['locked'=>true,'locked_until'=>(string)$row['locked_until'],'remaining_seconds'=>(int)$row['remaining_seconds'],'attempts_remaining'=>0];
}

function markazRecordLoginFailure(PDO $pdo, string $role, string $identifier, int $lockDays): array
{
    $lockDays = max(1, min(30, $lockDays));
    $pdo->prepare("INSERT INTO login_lockouts (role, identifier, failed_attempts, locked_until) VALUES (?, ?, 1, NULL)
        ON DUPLICATE KEY UPDATE
        locked_until = CASE
            WHEN locked_until IS NOT NULL AND locked_until > NOW() THEN locked_until
            WHEN failed_attempts + 1 >= 3 THEN DATE_ADD(NOW(), INTERVAL {$lockDays} DAY)
            ELSE NULL
        END,
        failed_attempts = failed_attempts + 1")->execute([$role, $identifier]);
    $stmt = $pdo->prepare('SELECT failed_attempts, locked_until, GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS remaining_seconds FROM login_lockouts WHERE role = ? AND identifier = ? LIMIT 1');
    $stmt->execute([$role, $identifier]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $seconds = (int) ($row['remaining_seconds'] ?? 0);
    return ['locked'=>$seconds > 0,'locked_until'=>$row['locked_until'] ?? null,'remaining_seconds'=>$seconds,'attempts_remaining'=>max(0,3-(int)($row['failed_attempts'] ?? 0))];
}

function markazClearLoginFailures(PDO $pdo, string $role, string $identifier): void
{
    $pdo->prepare('DELETE FROM login_lockouts WHERE role = ? AND identifier = ?')->execute([$role, $identifier]);
}
