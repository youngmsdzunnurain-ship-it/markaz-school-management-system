<?php
declare(strict_types=1);

require_once __DIR__ . '/session.php';

function requireLogin(): void
{
    startSecureSession();

    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Authentication required.'
        ]);
        exit;
    }
}

function currentAdminRole(): string
{
    requireLogin();

    return (string) ($_SESSION['admin_role'] ?? '');
}

function systemAccessEnabled(string $settingKey): bool
{
    require_once __DIR__ . '/../config/app.php';

    $stmt = db()->prepare(
        'SELECT setting_value
         FROM settings
         WHERE setting_key = ?
         LIMIT 1'
    );

    $stmt->execute([$settingKey]);

    $value = $stmt->fetchColumn();

    return $value === '1' || strtolower((string) $value) === 'true';
}

/**
 * Operational administration access.
 * Admin = Super Admin.
 * Exam Officer = operational administrator.
 */
function requireAdmin(): void
{
    $role = currentAdminRole();

    if (!in_array($role, ['admin', 'exam_officer'], true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Administrator or Exam Officer access required.'
        ]);
        exit;
    }

    if ($role === 'exam_officer' && !systemAccessEnabled('exam_officer_access')) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Exam Officer access is currently closed by the Admin.'
        ]);
        exit;
    }
}

/**
 * Super Admin only.
 * Kept under the existing function name for compatibility.
 */
function requireSeniorAdmin(): void
{
    $role = currentAdminRole();

    if ($role !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'message' => 'Admin access required.'
        ]);
        exit;
    }
}

/**
 * Result-management access.
 *
 * Admin:
 *   Always allowed.
 *
 * Exam Officer:
 *   Controlled by exam_officer_access.
 *
 * Teacher:
 *   Reserved for the future teacher authentication system.
 *   Teacher class/subject assignments will be checked separately.
 */
function requireResultStaff(): void
{
    $role = currentAdminRole();

    if ($role === 'admin') {
        return;
    }

    if ($role === 'exam_officer') {
        if (!systemAccessEnabled('exam_officer_access')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'success' => false,
                'message' => 'Exam Officer access is currently closed by the Admin.'
            ]);
            exit;
        }

        return;
    }

    if ($role === 'teacher') {
        if (!systemAccessEnabled('teacher_access')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'success' => false,
                'message' => 'Teacher access is currently closed by the Admin.'
            ]);
            exit;
        }

        return;
    }

    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => false,
        'message' => 'Result access required.'
    ]);
    exit;
}