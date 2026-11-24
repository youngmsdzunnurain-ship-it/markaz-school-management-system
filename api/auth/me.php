<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

startSecureSession();
requireLogin();

if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_role'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please sign in to continue.'
    ]);
    exit;
}

$stmt = db()->prepare(
    'SELECT id, name, email, role, profile_image
     FROM admins
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([$_SESSION['admin_id']]);

$admin = $stmt->fetch();

if (!$admin) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Administrator account not found.'
    ]);

    exit;
}

$role = (string) $admin['role'];
$accountId = (int) $admin['id'];
$canManageAttendance = $role === 'admin';
$managementPermissions = ['attendance' => false, 'students' => false, 'school_fees' => false];
if ($role === 'management') {
    $managementPermissionStmt = db()->prepare(
        'SELECT permission_key, is_allowed FROM account_permissions
         WHERE account_id = ? AND permission_key IN (?, ?, ?)'
    );
    $managementPermissionStmt->execute([
        $accountId, 'management_attendance', 'management_students', 'management_school_fees'
    ]);
    foreach ($managementPermissionStmt->fetchAll(PDO::FETCH_ASSOC) as $permissionRow) {
        $key = str_replace('management_', '', (string) $permissionRow['permission_key']);
        if (array_key_exists($key, $managementPermissions)) {
            $managementPermissions[$key] = (int) $permissionRow['is_allowed'] === 1;
        }
    }
    $canManageAttendance = $managementPermissions['attendance'];
}
if ($role === 'teacher') {
    $attendancePermissionStmt = db()->prepare(
        'SELECT is_allowed FROM account_permissions WHERE account_id = ? AND permission_key = ? LIMIT 1'
    );
    $attendancePermissionStmt->execute([$accountId, 'attendance_records']);
    $canManageAttendance = (int) $attendancePermissionStmt->fetchColumn() === 1;
}

$canAccessOnlineClass = $role === 'admin';
if (in_array($role, ['exam_officer', 'teacher', 'management'], true)) {
    $onlineClassPermissionStmt = db()->prepare(
        'SELECT is_allowed FROM account_permissions WHERE account_id = ? AND permission_key = ? LIMIT 1'
    );
    $onlineClassPermissionStmt->execute([$accountId, 'online_class_access']);
    $canAccessOnlineClass = (int) $onlineClassPermissionStmt->fetchColumn() === 1;
}

$resolvePermission = static function (string $permissionKey) use ($role, $accountId): bool {
    if (!in_array($permissionKey, ['print_results'], true)) {
        return false;
    }

    if ($role === 'admin') {
        return true;
    }

    if ($role === 'management') {
        return false;
    }

    if (!in_array($role, ['exam_officer', 'teacher', 'student'], true)) {
        return false;
    }

    if (!systemAccessEnabled($role . '_' . $permissionKey)) {
        return false;
    }

    if ($role !== 'teacher') {
        return true;
    }

    $pdo = db();
    $permissionStmt = $pdo->prepare(
        'SELECT is_allowed FROM account_permissions
         WHERE account_id = ? AND permission_key = ? LIMIT 1'
    );
    $permissionStmt->execute([$accountId, $permissionKey]);
    $individualPermission = $permissionStmt->fetchColumn();

    if ($individualPermission === false || (int) $individualPermission !== 1) {
        return false;
    }

    $assignmentStmt = $pdo->prepare(
        'SELECT 1 FROM teacher_assignments
         WHERE account_id = ? AND is_active = 1 AND subject_id IS NULL
         LIMIT 1'
    );
    $assignmentStmt->execute([$accountId]);

    return (bool) $assignmentStmt->fetchColumn();
};

echo json_encode([
    'success' => true,
    'admin' => [
        'id' => (int) $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $role,
        'profile_image' => $admin['profile_image'],
        'can_print_results' => $resolvePermission('print_results'),
        'can_manage_attendance' => $canManageAttendance,
        'can_access_teacher_attendance' => $role === 'admin' || ($role === 'management' && $managementPermissions['attendance'] === true),
        'management_permissions' => $managementPermissions,
        'can_access_online_class' => $canAccessOnlineClass
    ]
]);
