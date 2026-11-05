<?php
declare(strict_types=1);

function resetCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$login = file_get_contents(__DIR__ . '/../public/login.html');
$forgotPage = file_get_contents(__DIR__ . '/../public/forgot-password.html');
$resetPage = file_get_contents(__DIR__ . '/../public/reset-password.html');
$forgotApi = file_get_contents(__DIR__ . '/../api/auth/forgot-password.php');
$resetApi = file_get_contents(__DIR__ . '/../api/auth/reset-password.php');
$forgotJs = file_get_contents(__DIR__ . '/../public/js/forgot-password.js');
$resetJs = file_get_contents(__DIR__ . '/../public/js/reset-password.js');

resetCheck(str_contains($login, 'forgot-password.html'), 'Admin login must expose the forgot-password route');
resetCheck(str_contains($forgotApi, "role = 'admin'") && str_contains($forgotApi, 'is_active = 1') && str_contains($forgotApi, 'is_frozen = 0'), 'Reset requests must only target active, unfrozen Admin accounts');
resetCheck(str_contains($forgotApi, 'random_bytes(32)') && str_contains($forgotApi, "hash('sha256', $token)") && str_contains($forgotApi, 'INTERVAL 30 MINUTE'), 'Reset links must use cryptographically random, hashed, expiring tokens');
resetCheck(str_contains($forgotApi, 'mail(') && str_contains($forgotApi, 'MAIL_FROM'), 'Reset link must be sent to the registered email address through configured server mail');
resetCheck(str_contains($forgotApi, 'If an administrator account matches that email address'), 'Forgot-password responses must not reveal whether an email is registered');
resetCheck(str_contains($resetApi, 'password_hash($password, PASSWORD_DEFAULT)') && str_contains($resetApi, 'used_at = NOW()') && str_contains($resetApi, 'FOR UPDATE'), 'Reset completion must securely hash the password and consume the token atomically');
resetCheck(str_contains($resetApi, "role = 'admin'") && str_contains($resetApi, "DELETE FROM login_lockouts"), 'Reset completion must be limited to Admin accounts and clear their login lockout');
resetCheck(str_contains($forgotPage, 'forgotPasswordForm') && str_contains($forgotJs, 'forgot-password.php'), 'Forgot-password page must submit to its API');
resetCheck(str_contains($resetPage, 'resetPasswordForm') && str_contains($resetJs, 'reset-password.php'), 'Reset-password page must submit the token and new password');

echo "Admin password reset regression tests passed.\n";
