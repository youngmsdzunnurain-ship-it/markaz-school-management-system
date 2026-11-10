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

    // Recheck persistent account freezes on every authenticated request.
    require_once __DIR__ . '/../config/app.php';
    $freezeStmt = db()->prepare(
        'SELECT is_frozen FROM admins WHERE id = ? LIMIT 1'
    );
    $freezeStmt->execute([(int) $_SESSION['admin_id']]);
    $isFrozen = $freezeStmt->fetchColumn();

    if ($isFrozen === false || (bool) $isFrozen) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        http_response_code($isFrozen === false ? 401 : 403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'account_frozen' => $isFrozen !== false,
            'message' => $isFrozen === false
                ? 'This account is no longer available.'
                : 'This account has been blocked.'
        ]);
        exit;
    }

    // A device block also revokes an already authenticated session from that browser.
    $deviceHash = (string) ($_SESSION['device_token_hash'] ?? '');
    if (preg_match('/^[a-f0-9]{64}$/i', $deviceHash)) {
        $deviceStmt = db()->prepare(
            'SELECT is_blocked FROM login_devices
             WHERE account_id = ? AND device_token_hash = ? LIMIT 1'
        );
        $deviceStmt->execute([(int) $_SESSION['admin_id'], $deviceHash]);
        if ((int) $deviceStmt->fetchColumn() === 1) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'device_blocked' => true,
                'message' => 'This account has been blocked.'
            ]);
            exit;
        }
    }

    // Admin is exempt from portal switches; other roles are rechecked per request.
    $role = (string) ($_SESSION['admin_role'] ?? '');
    $portalSettings = [
        'exam_officer' => 'exam_officer_access',
        'teacher' => 'teacher_access',
        'student' => 'student_result_access',
        'management' => 'management_access',
    ];

    if (isset($portalSettings[$role]) && !systemAccessEnabled($portalSettings[$role])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'portal_closed' => true,
            'message' => 'Your portal access is currently closed by the Admin.'
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

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
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
 * Operational settings access for Admin and Exam Officer accounts.
 */
function requireAdminOrExamOfficer(): void
{
    requireAdmin();
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

/**
 * Enforce independently assigned role permissions.
 * Super Admin retains full access. Printing/exporting is never
 * automatically granted by a non-admin role.
 */

/**
 * Verify that a teacher is assigned to a conventional class and subject.
 * A NULL subject_id assignment grants all subjects in that class.
 */
function requireTeacherAssignment(
    int $classId,
    int $subjectId,
    string $classType = 'conventional'
): void
{
    if (currentAdminRole() !== 'teacher') {
        return;
    }

    if (!in_array($classType, ['conventional', 'arabic'], true)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid class type.'
        ]);
        exit;
    }

    $accountId = (int)($_SESSION['admin_id'] ?? 0);

    if ($accountId < 1 || $classId < 1 || $subjectId < 1) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'You are not assigned to this class and subject.'
        ]);
        exit;
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM teacher_assignments
         WHERE account_id = ?
           AND class_type = ?
           AND class_id = ?
           AND is_active = 1
           AND (subject_id = ? OR subject_id IS NULL)
         LIMIT 1"
    );
    $stmt->execute([$accountId, $classType, $classId, $subjectId]);

    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'You are not assigned to this class and subject.'
        ]);
        exit;
    }
}

/** Check an individually assigned Management workspace permission. */
function managementPermissionAllowed(string $permissionKey): bool
{
    if (!in_array($permissionKey, ['attendance', 'students', 'school_fees'], true)) {
        return false;
    }

    if (currentAdminRole() !== 'management') {
        return false;
    }

    $stmt = db()->prepare(
        'SELECT is_allowed FROM account_permissions
         WHERE account_id = ? AND permission_key = ? LIMIT 1'
    );
    $stmt->execute([(int) ($_SESSION['admin_id'] ?? 0), 'management_' . $permissionKey]);
    return (int) $stmt->fetchColumn() === 1;
}

/** Require an individually assigned Online Class workspace permission. */
function requireOnlineClassAccess(): void
{
    $role = currentAdminRole();
    if ($role === 'admin') return;
    if (!in_array($role, ['exam_officer', 'teacher', 'management'], true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Online Class staff access is required.']);
        exit;
    }
    $stmt = db()->prepare('SELECT is_allowed FROM account_permissions WHERE account_id = ? AND permission_key = ? LIMIT 1');
    $stmt->execute([(int) ($_SESSION['admin_id'] ?? 0), 'online_class_access']);
    if ((int) $stmt->fetchColumn() !== 1) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Online Class access has not been assigned to this account.']);
        exit;
    }
}

function requireManagementPermission(string $permissionKey): void
{
    if (!managementPermissionAllowed($permissionKey)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'This workspace has not been assigned to your Management account.'
        ]);
        exit;
    }
}

/** Keep existing Admin/Exam Officer access while allowing assigned managers. */
function requireAdminOrManagementPermission(string $permissionKey): void
{
    $role = currentAdminRole();
    if (in_array($role, ['admin', 'exam_officer'], true)) {
        requireAdmin();
        return;
    }
    if ($role === 'management') {
        requireManagementPermission($permissionKey);
        return;
    }
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'You do not have access to this workspace.']);
    exit;
}

/** Access to the independent MARKAZ Online Class workspace. */
function onlineClassPermissionAllowed(): bool
{
    $role = currentAdminRole();
    if ($role === 'admin') {
        return true;
    }
    if (!in_array($role, ['exam_officer', 'teacher', 'management'], true)) {
        return false;
    }
    $accountId = (int) ($_SESSION['admin_id'] ?? 0);
    if ($accountId < 1) {
        return false;
    }
    $stmt = db()->prepare(
        'SELECT is_allowed FROM account_permissions
         WHERE account_id = ? AND permission_key = ? LIMIT 1'
    );
    $stmt->execute([$accountId, 'online_class_access']);
    return (int) $stmt->fetchColumn() === 1;
}

function requireOnlineClassAccess(): void
{
    if (!onlineClassPermissionAllowed()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'MARKAZ Online Class access has not been assigned to this account.'
        ]);
        exit;
    }
}

/** Grant Online Class workspace access only to individually assigned staff. */
function requireOnlineClassAccess(): void
{
    $role = currentAdminRole();
    if ($role === 'admin') return;
    if (!in_array($role, ['exam_officer', 'teacher', 'management'], true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Staff access is required.']);
        exit;
    }
    $stmt = db()->prepare(
        'SELECT is_allowed FROM account_permissions
         WHERE account_id = ? AND permission_key = ? LIMIT 1'
    );
    $stmt->execute([(int) ($_SESSION['admin_id'] ?? 0), 'online_class_access']);
    if ((int) $stmt->fetchColumn() !== 1) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Online Class access has not been assigned to this account.']);
        exit;
    }
}

function requireRolePermission(
    string $permission,
    ?int $classId = null,
    ?string $classType = null
): void
{
    $role = currentAdminRole();

    $permissionKeys = [
        "print_results",
        "export_results",
    ];

    if (!in_array($permission, $permissionKeys, true)) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Permission denied."
        ]);
        exit;
    }

    $roleSettings = [
        "admin" => "admin",
        "exam_officer" => "exam_officer",
        "teacher" => "teacher",
        "student" => "student",
        "management" => "management",
    ];

    if (!isset($roleSettings[$role])) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Permission denied."
        ]);
        exit;
    }

    $settingKey = $roleSettings[$role] . "_" . $permission;
    $allowed = systemAccessEnabled($settingKey);

    if ($role === 'teacher') {
        $accountId = (int) ($_SESSION['admin_id'] ?? 0);
        $requestedClassId = $classId ?? (filter_var($_GET['class_id'] ?? null, FILTER_VALIDATE_INT) ?: null);
        $requestedClassType = $classType ?? (string) ($_GET['class_type'] ?? 'conventional');

        if (
            !$allowed
            || $accountId < 1
            || !$requestedClassId
            || !in_array($requestedClassType, ['conventional', 'arabic'], true)
        ) {
            $allowed = false;
        } else {
            $permissionStmt = db()->prepare(
                'SELECT is_allowed FROM account_permissions
                 WHERE account_id = ? AND permission_key = ? LIMIT 1'
            );
            $permissionStmt->execute([$accountId, $permission]);
            $individualPermission = $permissionStmt->fetchColumn();

            $assignmentStmt = db()->prepare(
                'SELECT 1 FROM teacher_assignments
                 WHERE account_id = ? AND class_type = ? AND class_id = ?
                   AND is_active = 1 AND subject_id IS NULL LIMIT 1'
            );
            $assignmentStmt->execute([$accountId, $requestedClassType, $requestedClassId]);

            $allowed = $individualPermission !== false
                && (int) $individualPermission === 1
                && (bool) $assignmentStmt->fetchColumn();
        }
    }

    if (!$allowed) {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "You do not have permission to perform this action."
        ]);
        exit;
    }
}