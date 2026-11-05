<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Deliver a MARKAZ account email through the configured authenticated SMTP
 * transport. SMTP credentials are read only from the server-side environment.
 */
function sendMarkazPasswordResetEmail(
    string $recipientEmail,
    string $recipientName,
    string $resetUrl
): void {
    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Composer dependencies are missing. Run composer install.');
    }
    require_once $autoload;

    $host = trim((string) ($_ENV['SMTP_HOST'] ?? ''));
    $username = trim((string) ($_ENV['SMTP_USERNAME'] ?? ''));
    $password = (string) ($_ENV['SMTP_PASSWORD'] ?? '');
    $port = (int) ($_ENV['SMTP_PORT'] ?? 587);
    $encryption = strtolower(trim((string) ($_ENV['SMTP_ENCRYPTION'] ?? 'tls')));
    $from = trim((string) ($_ENV['MAIL_FROM'] ?? $username));
    $fromName = trim((string) ($_ENV['MAIL_FROM_NAME'] ?? 'MARKAZ'));

    if ($host === '' || $username === '' || $password === '') {
        throw new RuntimeException('SMTP_HOST, SMTP_USERNAME and SMTP_PASSWORD must be configured.');
    }
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('SMTP_PORT must be a valid TCP port.');
    }
    if (!in_array($encryption, ['tls', 'ssl'], true)) {
        throw new RuntimeException('SMTP_ENCRYPTION must be tls or ssl.');
    }
    if (!filter_var($username, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('SMTP_USERNAME and MAIL_FROM must be valid email addresses.');
    }
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The password-reset recipient email is invalid.');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $username;
    $mail->Password = $password;
    $mail->Port = $port;
    $mail->SMTPSecure = $encryption === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Timeout = 20;
    $mail->SMTPDebug = 0;

    $mail->setFrom($from, $fromName);
    $mail->addAddress($recipientEmail, $recipientName);
    $mail->isHTML(true);
    $mail->Subject = 'MARKAZ administrator password reset';

    $safeName = htmlspecialchars($recipientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $mail->Body = "<!doctype html><html><body style=\"font-family:Arial,sans-serif;color:#183326;line-height:1.6\">"
        . "<h2>MARKAZ password reset</h2><p>Hello {$safeName},</p>"
        . "<p>A password reset was requested for your administrator account.</p>"
        . "<p><a href=\"{$safeUrl}\" style=\"display:inline-block;padding:12px 20px;background:#176b45;color:#fff;text-decoration:none;border-radius:8px\">Reset Password</a></p>"
        . "<p>This link expires in 30 minutes and can only be used once. If you did not request this, you can ignore this email.</p></body></html>";
    $mail->AltBody = "Hello {$recipientName},\n\nA password reset was requested for your MARKAZ administrator account. "
        . "Open this link within 30 minutes: {$resetUrl}\n\nIf you did not request this, ignore this email.";

    $mail->send();
}
