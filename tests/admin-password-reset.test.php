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
$mailer = file_get_contents(__DIR__ . '/../app/services/password-reset-mailer.php');
$composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

resetCheck(str_contains($login, 'forgot-password.html?open=1'), 'Admin login must open the shared-book forgot-password route');
resetCheck(str_contains($forgotApi, "role = 'admin'") && str_contains($forgotApi, 'is_active = 1') && str_contains($forgotApi, 'is_frozen = 0'), 'Reset requests must only target active, unfrozen Admin accounts');
resetCheck(str_contains($forgotApi, 'random_bytes(32)') && str_contains($forgotApi, "hash('sha256'") && str_contains($forgotApi, 'INTERVAL 30 MINUTE'), 'Reset links must use cryptographically random, hashed, expiring tokens');
resetCheck(str_contains($forgotApi, 'sendMarkazPasswordResetEmail') && str_contains($forgotApi, "(string) \$admin['email']"), 'Reset link must be sent to the current registered Admin email');
resetCheck(str_contains($mailer, 'smtp.gmail.com') === false && str_contains($mailer, "SMTP_HOST") && str_contains($mailer, 'PHPMailer') && str_contains($mailer, 'SMTPAuth = true'), 'Password reset mailer must use configurable authenticated SMTP');
resetCheck(isset($composer['require']['phpmailer/phpmailer']), 'PHPMailer must be declared as a Composer dependency');
resetCheck(str_contains($mailer, "SMTP_PASSWORD") && str_contains($mailer, "SMTP_USERNAME"), 'SMTP credentials must be loaded from server environment variables');
resetCheck(str_contains($forgotApi, 'If an administrator account matches that email address'), 'Forgot-password responses must not reveal whether an email is registered');
resetCheck(str_contains($resetApi, 'CREATE TABLE IF NOT EXISTS login_lockouts') && str_contains($resetApi, "DELETE FROM login_lockouts WHERE role = 'admin' AND identifier = ?"), 'Password reset must safely initialize and clear lockouts even before a login has created the table');
resetCheck(str_contains($resetApi, 'password_hash($password, PASSWORD_DEFAULT)') && str_contains($resetApi, 'used_at = NOW()') && str_contains($resetApi, 'FOR UPDATE'), 'Reset completion must securely hash the password and consume the token atomically');
resetCheck(str_contains($resetApi, "role = 'admin'") && str_contains($resetApi, "is_frozen = 0"), 'Reset completion must be limited to eligible Admin accounts');
resetCheck(str_contains($forgotPage, 'book-auth.css') && str_contains($forgotPage, 'id="book"') && str_contains($forgotPage, 'data-auth-form'), 'Forgot-password page must use the shared book authentication design');
resetCheck(str_contains($resetPage, 'book-auth.css') && str_contains($resetPage, 'id="book"') && str_contains($resetPage, 'data-toggle-password'), 'Reset-password page must use the shared book design and show/hide controls');
resetCheck(substr_count($resetPage, 'data-toggle-password') === 2, 'Both new-password fields must have independent show/hide controls');
resetCheck(str_contains($resetJs, 'password_confirmation: confirmation') && str_contains($resetJs, 'login.html?open=1'), 'Reset completion must submit confirmation and return to the opened Admin login');
resetCheck(is_file(__DIR__ . '/../public/api/auth/forgot-password.php') && str_contains(file_get_contents(__DIR__ . '/../public/api/auth/forgot-password.php'), "require_once __DIR__ . '/../../../api/auth/forgot-password.php'"), 'Public forgot-password API route must forward to the secure API endpoint');
resetCheck(is_file(__DIR__ . '/../public/api/auth/reset-password.php') && str_contains(file_get_contents(__DIR__ . '/../public/api/auth/reset-password.php'), "require_once __DIR__ . '/../../../api/auth/reset-password.php'"), 'Public reset-password API route must forward to the secure API endpoint');

echo "Admin password reset regression tests passed.\n";
