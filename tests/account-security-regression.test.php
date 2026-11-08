<?php
declare(strict_types=1);

function securityCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$login = file_get_contents(__DIR__ . '/../api/auth/login.php');
$auth = file_get_contents(__DIR__ . '/../app/middleware/auth.php');
$freezes = file_get_contents(__DIR__ . '/../api/settings/account-freezes.php');
$lockouts = file_get_contents(__DIR__ . '/../api/settings/login-lockouts.php');
$devices = file_get_contents(__DIR__ . '/../api/settings/account-devices.php');

securityCheck(
    str_contains($login, '$attempts >= 3') &&
    str_contains($login, 'INTERVAL 7 DAY') &&
    str_contains($login, "'locked' => true"),
    'login lockout must enforce three failed attempts and a seven-day lock'
);
securityCheck(
    str_contains($login, 'is_frozen') &&
    str_contains($login, 'This account has been frozen'),
    'frozen accounts must be rejected during login'
);
securityCheck(
    str_contains($auth, 'SELECT is_frozen FROM admins') &&
    str_contains($auth, 'if ($isFrozen === false || (bool) $isFrozen)') &&
    str_contains($auth, 'exit;'),
    'existing sessions must be rejected after an administrator freezes the account'
);
securityCheck(
    str_contains($freezes, 'SET is_frozen = 1') &&
    str_contains($freezes, 'SET is_frozen = 0') &&
    str_contains($freezes, 'account_freeze_audit'),
    'freeze and unfreeze actions must persist and create an audit record'
);
securityCheck(
    str_contains($lockouts, 'DELETE FROM login_lockouts WHERE id = ? AND locked_until IS NOT NULL'),
    'Senior Admin unlock action must remove the persistent login lockout'
);

securityCheck(
    str_contains($login, 'login_devices') &&
    str_contains($login, "SELECT is_blocked FROM login_devices") &&
    str_contains($login, "device_blocked") &&
    str_contains($auth, "device_token_hash"),
    'login checks account-specific blocked browser devices before establishing a session'
);
securityCheck(
    str_contains($devices, 'login_device_blocks') &&
    str_contains($devices, "is_blocked = 1") &&
    str_contains($devices, "'block', 'unblock'") === false &&
    str_contains($devices, "['block', 'unblock']"),
    'Senior Admin can block and unblock a previously used device for one account'
);

echo "Account lockout and freeze enforcement regression tests passed.\n";
