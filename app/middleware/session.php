<?php
declare(strict_types=1);

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionPath = dirname(__DIR__, 2) . '/storage/sessions';

    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0700, true);
    }

    session_save_path($sessionPath);

    session_name('MARKAZ_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_start();
}


/**
 * Start an isolated session for public student result access.
 * It deliberately uses a different cookie name from staff authentication.
 */
function startStudentPortalSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionPath = dirname(__DIR__, 2) . '/storage/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0700, true);
    }

    session_save_path($sessionPath);
    session_name('MARKAZ_STUDENT_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    session_start();
}
