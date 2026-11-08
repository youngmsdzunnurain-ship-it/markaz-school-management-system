<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireSeniorAdmin();

$pdo = db();

function accountResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function accountInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {
        accountResponse([
            'success' => false,
            'message' => 'Send a valid JSON request.'
        ], 400);
    }

    return $data;
}

function validateTeacherAssignments(PDO $pdo, array $assignments): array
{
    $validated = [];
    $seen = [];

    foreach ($assignments as $assignment) {
        if (!is_array($assignment)) {
            throw new InvalidArgumentException('Invalid teacher assignment.');
        }

        $type = $assignment['class_type'] ?? '';
        $classId = filter_var(
            $assignment['class_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        $subjectValue = $assignment['subject_id'] ?? null;
        $subjectId = ($subjectValue === null || $subjectValue === '')
            ? null
            : filter_var($subjectValue, FILTER_VALIDATE_INT);

        if (
            !in_array($type, ['conventional', 'arabic'], true)
            || !$classId || $classId < 1
            || ($subjectId !== null && (!$subjectId || $subjectId < 1))
        ) {
            throw new InvalidArgumentException('A teacher assignment contains invalid IDs or class type.');
        }

        $table = $type === 'conventional' ? 'classes' : 'arabic_classes';
        $classStmt = $pdo->prepare("SELECT id FROM {$table} WHERE id = ? LIMIT 1");
        $classStmt->execute([$classId]);

        if (!$classStmt->fetchColumn()) {
            throw new InvalidArgumentException('A selected class does not exist.');
        }

        if ($subjectId !== null) {
            $mappingTable = $type === 'arabic' ? 'arabic_class_subjects' : 'class_subjects';
            $classColumn = $type === 'arabic' ? 'arabic_class_id' : 'class_id';

            $subjectStmt = $pdo->prepare(
                "SELECT 1 FROM {$mappingTable} cs
                 INNER JOIN subjects s ON s.id = cs.subject_id
                 WHERE cs.{$classColumn} = ? AND cs.subject_id = ?
                 LIMIT 1"
            );
            $subjectStmt->execute([$classId, $subjectId]);

            if (!$subjectStmt->fetchColumn()) {
                throw new InvalidArgumentException(
                    'The selected subject is not assigned to that class.'
                );
            }
        }

        $key = $type . ':' . $classId . ':' . ($subjectId ?? 'all');

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $validated[] = [
            'class_type' => $type,
            'class_id' => (int) $classId,
            'subject_id' => $subjectId === null ? null : (int) $subjectId,
        ];
    }

    return $validated;
}

function saveAccountPermission(
    PDO $pdo,
    int $accountId,
    string $key,
    bool $allowed
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO account_permissions
            (account_id, permission_key, is_allowed)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE
            is_allowed = VALUES(is_allowed)'
    );

    $stmt->execute([$accountId, $key, $allowed ? 1 : 0]);
}

function strongAccountPassword(string $password): bool {
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^A-Za-z0-9]/', $password) === 1;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $accounts = $pdo->query(
        'SELECT
            a.id,
            a.name,
            a.email,
            a.username,
            a.role,
            a.is_active,
            a.student_id,
            a.created_at,
            s.admission_number,
            s.first_name AS student_first_name,
            s.middle_name AS student_middle_name,
            s.last_name AS student_last_name
         FROM admins a
         LEFT JOIN students s ON s.id = a.student_id
         ORDER BY a.name ASC'
    )->fetchAll(PDO::FETCH_ASSOC);

    $permissionStmt = $pdo->query(
        'SELECT account_id, permission_key, is_allowed
         FROM account_permissions
         WHERE permission_key IN ("print_results", "export_results", "attendance_records")'
    );

    $permissions = [];
    foreach ($permissionStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $permissions[(int) $row['account_id']][$row['permission_key']]
            = (int) $row['is_allowed'] === 1;
    }


    // Resolve inherited print/export permissions for accounts without an override.
    $rolePermissionSettings = [
        'admin' => [
            'print_results' => 'admin_print_results',
            'export_results' => 'admin_export_results',
        ],
        'exam_officer' => [
            'print_results' => 'exam_officer_print_results',
            'export_results' => 'exam_officer_export_results',
        ],
        'teacher' => [
            'print_results' => 'teacher_print_results',
            'export_results' => 'teacher_export_results',
        ],
        'student' => [
            'print_results' => 'student_print_results',
            'export_results' => 'student_export_results',
        ],
        'management' => [
            'print_results' => 'management_print_results',
            'export_results' => 'management_export_results',
        ],
    ];

    $permissionSettingKeys = [
        'admin_print_results', 'admin_export_results',
        'exam_officer_print_results', 'exam_officer_export_results',
        'teacher_print_results', 'teacher_export_results',
        'student_print_results', 'student_export_results',
        'management_print_results', 'management_export_results',
    ];

    $settingPlaceholders = implode(',', array_fill(0, count($permissionSettingKeys), '?'));
    $settingStmt = $pdo->prepare(
        "SELECT setting_key, setting_value
         FROM settings
         WHERE setting_key IN ($settingPlaceholders)"
    );
    $settingStmt->execute($permissionSettingKeys);

    $rolePermissionValues = [];
    foreach ($settingStmt->fetchAll(PDO::FETCH_ASSOC) as $setting) {
        $rolePermissionValues[$setting['setting_key']] = in_array(
            strtolower(trim((string) $setting['setting_value'])),
            ['1', 'true', 'yes', 'on'],
            true
        );
    }

    $assignmentStmt = $pdo->query(
        'SELECT id, account_id, class_type, class_id, subject_id, is_active
         FROM teacher_assignments
         WHERE is_active = 1
         ORDER BY account_id, class_type, class_id, subject_id'
    );

    $assignments = [];
    foreach ($assignmentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $assignments[(int) $row['account_id']][] = [
            'class_type' => $row['class_type'],
            'class_id' => (int) $row['class_id'],
            'subject_id' => $row['subject_id'] === null
                ? null : (int) $row['subject_id'],
        ];
    }

    foreach ($accounts as &$account) {
        $id = (int) $account['id'];
        $account['id'] = $id;
        $account['is_active'] = (bool) $account['is_active'];
        $account['student_id'] = $account['student_id'] === null
            ? null : (int) $account['student_id'];
        $roleSettings = $rolePermissionSettings[(string) $account['role']] ?? [];
        $effectivePermissions = [];
        $hasFullClassAccess = (string) $account['role'] === 'teacher'
            && !empty(array_filter(
                $assignments[$id] ?? [],
                static fn(array $assignment): bool => $assignment['subject_id'] === null
            ));
        foreach (['print_results', 'export_results'] as $permissionKey) {
            $settingKey = $roleSettings[$permissionKey] ?? '';
            $effectivePermissions[$permissionKey] = (string) $account['role'] === 'teacher'
                ? ($hasFullClassAccess && ($permissions[$id][$permissionKey] ?? false))
                : ($settingKey !== ''
                    ? ($rolePermissionValues[$settingKey] ?? false)
                    : false);
        }

        $effectivePermissions['attendance_records'] = $permissions[$id]['attendance_records'] ?? false;
        $account['permissions'] = $effectivePermissions;
        $account['assignments'] = $assignments[$id] ?? [];
    }
    unset($account);

    $students = $pdo->query(
        'SELECT
            s.id,
            s.admission_number,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.class_id,
            s.arabic_class_id,
            s.is_active,
            c.name AS class_name,
            ac.name AS arabic_class_name
         FROM students s
         LEFT JOIN classes c ON c.id = s.class_id
         LEFT JOIN arabic_classes ac ON ac.id = s.arabic_class_id
         ORDER BY s.last_name, s.first_name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $classes = $pdo->query(
        'SELECT id, name FROM classes ORDER BY name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $arabicClasses = $pdo->query(
        'SELECT id, name FROM arabic_classes ORDER BY name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $subjects = $pdo->query(
        'SELECT id, name FROM subjects ORDER BY name'
    )->fetchAll(PDO::FETCH_ASSOC);

    $arabicClassSubjects = $pdo->query(
        'SELECT arabic_class_id, subject_id
         FROM arabic_class_subjects
         ORDER BY arabic_class_id, subject_id'
    )->fetchAll(PDO::FETCH_ASSOC);

    $classSubjects = $pdo->query(
        'SELECT class_id, subject_id
         FROM class_subjects
         ORDER BY class_id, subject_id'
    )->fetchAll(PDO::FETCH_ASSOC);

    accountResponse([
        'success' => true,
        'accounts' => $accounts,
        'students' => $students,
        'classes' => $classes,
        'arabic_classes' => $arabicClasses,
        'arabic_class_subjects' => $arabicClassSubjects,
        'class_subjects' => $classSubjects,
        'subjects' => $subjects,
    ]);
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    accountResponse([
        'success' => false,
        'message' => 'Use GET to list accounts or POST to create/update an account.'
    ], 405);
}

$data = accountInput();
$action = $data['action'] ?? '';
$allowedRoles = ['admin', 'exam_officer', 'teacher', 'student', 'management'];

if (!in_array($action, ['create', 'update'], true)) {
    accountResponse(['success' => false, 'message' => 'Invalid account action.'], 400);
}

$accountId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

if ($action === 'update' && (!$accountId || $accountId < 1)) {
    accountResponse(['success' => false, 'message' => 'A valid account ID is required.'], 400);
}

$name = trim((string) ($data['name'] ?? ''));
$email = strtolower(trim((string) ($data['email'] ?? '')));
$username = trim((string) ($data['username'] ?? ''));
$role = (string) ($data['role'] ?? '');
$emailValue = $role === 'teacher' ? null : $email;
$password = (string) ($data['password'] ?? '');
$isActive = filter_var(
    $data['is_active'] ?? true,
    FILTER_VALIDATE_BOOLEAN,
    FILTER_NULL_ON_FAILURE
);

$studentValue = $data['student_id'] ?? null;
$studentId = ($studentValue === null || $studentValue === '')
    ? null
    : filter_var($studentValue, FILTER_VALIDATE_INT);

if (
    $name === '' || mb_strlen($name) > 120
    || ($role !== 'teacher' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190))
    || ($role === 'teacher' && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])[A-Za-z0-9]{4,60}$/', $username))
    || !in_array($role, $allowedRoles, true)
    || $isActive === null
    || ($studentValue !== null && $studentValue !== ''
        && (!$studentId || $studentId < 1))
) {
    accountResponse([
        'success' => false,
        'message' => 'Check the name, email, role, active status and student link.'
    ], 400);
}

if ($role === 'student' && $studentId === null) {
    accountResponse([
        'success' => false,
        'message' => 'A student account must be linked to a specific student record.'
    ], 400);
}

if ($role !== 'student' && $studentId !== null) {
    accountResponse([
        'success' => false,
        'message' => 'Only student accounts can be linked to a student record.'
    ], 400);
}

if ($action === 'create' && $role === 'admin') {
    accountResponse([
        'success' => false,
        'message' => 'Senior Admin accounts cannot be created through regular account management.'
    ], 403);
}

if ($action === 'create' && !strongAccountPassword($password)) {
    accountResponse(['success' => false, 'message' => 'Passwords must be at least 8 characters and include uppercase, lowercase, a number and a symbol.'], 400);
}

if ($action === 'update' && $password !== '' && !strongAccountPassword($password)) {
    accountResponse(['success' => false, 'message' => 'Passwords must be at least 8 characters and include uppercase, lowercase, a number and a symbol.'], 400);
}

try {
    $pdo->beginTransaction();

    if ($action === 'update') {
        $find = $pdo->prepare(
            'SELECT id, role, is_active FROM admins WHERE id = ? FOR UPDATE'
        );
        $find->execute([$accountId]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            throw new InvalidArgumentException('Account not found.');
        }

        if ($existing['role'] !== 'admin' && $role === 'admin') {
            throw new InvalidArgumentException(
                'Regular accounts cannot be promoted to Senior Admin here.'
            );
        }

        $currentId = (int) ($_SESSION['admin_id'] ?? 0);

        if ($accountId === $currentId && (!$isActive || $role !== 'admin')) {
            throw new InvalidArgumentException(
                'You cannot deactivate or change the role of your own Senior Admin account.'
            );
        }

        if (
            $existing['role'] === 'admin'
            && ($role !== 'admin' || !$isActive)
        ) {
            throw new InvalidArgumentException(
                'A Senior Admin account cannot be demoted or deactivated here.'
            );
        }

        if ($emailValue !== null) {
            $checkEmail = $pdo->prepare(
                'SELECT id FROM admins WHERE email = ? AND id <> ? LIMIT 1'
            );
            $checkEmail->execute([$emailValue, $accountId]);
            if ($checkEmail->fetchColumn()) {
                throw new InvalidArgumentException('That email address is already in use.');
            }
        }

        if ($role === 'teacher') {
            $checkUsername = $pdo->prepare(
                'SELECT id FROM admins WHERE username = ? AND id <> ? LIMIT 1'
            );
            $checkUsername->execute([$username, $accountId]);
            if ($checkUsername->fetchColumn()) {
                throw new InvalidArgumentException('That teacher username is already in use.');
            }
        }

        if ($studentId !== null) {
            $studentCheck = $pdo->prepare(
                'SELECT id FROM students WHERE id = ? AND is_active = 1 LIMIT 1'
            );
            $studentCheck->execute([$studentId]);

            if (!$studentCheck->fetchColumn()) {
                throw new InvalidArgumentException(
                    'Choose an existing active student record.'
                );
            }
        }

        if ($password !== '') {
            $update = $pdo->prepare(
                'UPDATE admins
                 SET name = ?, email = ?, username = ?, role = ?, is_active = ?,
                     student_id = ?, password_hash = ?
                 WHERE id = ?'
            );
            $update->execute([
                $name, $emailValue, ($role === 'teacher' ? $username : null), $role,
                $isActive ? 1 : 0, $studentId,
                password_hash($password, PASSWORD_DEFAULT), $accountId
            ]);
        } else {
            $update = $pdo->prepare(
                'UPDATE admins
                 SET name = ?, email = ?, username = ?, role = ?, is_active = ?, student_id = ?
                 WHERE id = ?'
            );
            $update->execute([
                $name, $emailValue, ($role === 'teacher' ? $username : null),
                $role, $isActive ? 1 : 0, $studentId, $accountId
            ]);
        }

        $targetId = (int) $accountId;
    } else {
        if ($emailValue !== null) {
            $checkEmail = $pdo->prepare(
                'SELECT id FROM admins WHERE email = ? LIMIT 1'
            );
            $checkEmail->execute([$emailValue]);
            if ($checkEmail->fetchColumn()) {
                throw new InvalidArgumentException('That email address is already in use.');
            }
        }

        if ($role === 'teacher') {
            $checkUsername = $pdo->prepare(
                'SELECT id FROM admins WHERE username = ? LIMIT 1'
            );
            $checkUsername->execute([$username]);
            if ($checkUsername->fetchColumn()) {
                throw new InvalidArgumentException('That teacher username is already in use.');
            }
        }

        if ($studentId !== null) {
            $studentCheck = $pdo->prepare(
                'SELECT id FROM students WHERE id = ? AND is_active = 1 LIMIT 1'
            );
            $studentCheck->execute([$studentId]);

            if (!$studentCheck->fetchColumn()) {
                throw new InvalidArgumentException(
                    'Choose an existing active student record.'
                );
            }
        }

        $insert = $pdo->prepare(
            'INSERT INTO admins
                (name, email, username, role, password_hash, is_active, student_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $name,
            $emailValue,
            ($role === 'teacher' ? $username : null),
            $role,
            password_hash($password, PASSWORD_DEFAULT),
            $isActive ? 1 : 0,
            $studentId
        ]);

        $targetId = (int) $pdo->lastInsertId();
    }

    if ($role === 'teacher' && array_key_exists('assignments', $data)) {
        if (!is_array($data['assignments'])) {
            throw new InvalidArgumentException('Teacher assignments must be a list.');
        }

        $validatedAssignments = validateTeacherAssignments(
            $pdo,
            $data['assignments']
        );

        $delete = $pdo->prepare(
            'DELETE FROM teacher_assignments WHERE account_id = ?'
        );
        $delete->execute([$targetId]);

        $insertAssignment = $pdo->prepare(
            'INSERT INTO teacher_assignments
                (account_id, class_type, class_id, subject_id, is_active)
             VALUES (?, ?, ?, ?, 1)'
        );

        foreach ($validatedAssignments as $assignment) {
            $insertAssignment->execute([
                $targetId,
                $assignment['class_type'],
                $assignment['class_id'],
                $assignment['subject_id']
            ]);
        }
    } elseif ($role !== 'teacher') {
        $delete = $pdo->prepare(
            'DELETE FROM teacher_assignments WHERE account_id = ?'
        );
        $delete->execute([$targetId]);
    }

    if ($role === 'teacher') {
        $fullClassCheck = $pdo->prepare(
            'SELECT 1 FROM teacher_assignments
             WHERE account_id = ? AND is_active = 1 AND subject_id IS NULL
             LIMIT 1'
        );
        $fullClassCheck->execute([$targetId]);
        $hasFullClassAccess = (bool) $fullClassCheck->fetchColumn();

        saveAccountPermission($pdo, $targetId, 'attendance_records', filter_var($data['attendance_records'] ?? false, FILTER_VALIDATE_BOOLEAN));
        saveAccountPermission(
            $pdo,
            $targetId,
            'print_results',
            $hasFullClassAccess && filter_var($data['print_results'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );
        saveAccountPermission(
            $pdo,
            $targetId,
            'export_results',
            $hasFullClassAccess && filter_var($data['export_results'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );
    } else {
        saveAccountPermission($pdo, $targetId, 'attendance_records', false);
        saveAccountPermission($pdo, $targetId, 'print_results', false);
        saveAccountPermission($pdo, $targetId, 'export_results', false);
    }

    $pdo->commit();

    accountResponse([
        'success' => true,
        'message' => $action === 'create'
            ? 'Account created successfully.'
            : 'Account updated successfully.',
        'account_id' => $targetId,
    ]);
} catch (InvalidArgumentException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    accountResponse([
        'success' => false,
        'message' => $error->getMessage()
    ], 400);
} catch (PDOException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Account management database error: ' . $error->getMessage());

    accountResponse([
        'success' => false,
        'message' => 'The account could not be saved. Check that the email and student link are unique and try again.'
    ], 409);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Account management error: ' . $error->getMessage());

    accountResponse([
        'success' => false,
        'message' => 'An unexpected error prevented the account from being saved.'
    ], 500);
}
