<?php
declare(strict_types=1);

/**
 * Shared rules for the daily attendance API.
 * Daily records intentionally remain separate from termly result assessments.
 */

function attendanceJsonError(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function attendanceIsValidDate(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();

    return $parsed !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsed->format('Y-m-d') === $date;
}

function attendanceIsValidStatus(string $status): bool
{
    return in_array($status, ['present', 'absent', 'late', 'excused'], true);
}

function attendanceClassTable(string $classType): ?string
{
    return match ($classType) {
        'conventional' => 'classes',
        'arabic' => 'arabic_classes',
        default => null,
    };
}

function attendanceHasArabicClassWithName(PDO $pdo, string $className): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM arabic_classes
         WHERE is_active = 1 AND LOWER(TRIM(name)) = LOWER(TRIM(?))
         LIMIT 1'
    );
    $stmt->execute([$className]);
    return (bool) $stmt->fetchColumn();
}

function attendanceValidateDate(string $date, bool $allowFuture = false): string
{
    if (!attendanceIsValidDate($date)) {
        attendanceJsonError(422, 'Choose a valid attendance date.');
    }

    $today = new DateTimeImmutable('today');

    if (!$allowFuture && new DateTimeImmutable($date) > $today) {
        attendanceJsonError(422, 'Attendance cannot be recorded for a future date.');
    }

    return $date;
}

function attendanceRequireStaff(): string
{
    $role = currentAdminRole();

    if (in_array($role, ['admin', 'exam_officer'], true)) {
        requireAdmin();
        return $role;
    }

    if ($role === 'management') {
        requireManagementPermission('attendance');
        return $role;
    }

    if ($role === 'teacher') {
        requireResultStaff();
        $permission = db()->prepare(
            'SELECT is_allowed FROM account_permissions
             WHERE account_id = ? AND permission_key = ? LIMIT 1'
        );
        $permission->execute([(int) ($_SESSION['admin_id'] ?? 0), 'attendance_records']);
        if ((int) $permission->fetchColumn() !== 1) {
            attendanceJsonError(403, 'Attendance recording has not been enabled for your teacher account.');
        }
        return $role;
    }

    attendanceJsonError(403, 'An authorised staff account is required.');
}

function attendanceRequireClassAccess(PDO $pdo, string $classType, int $classId): array
{
    $table = attendanceClassTable($classType);

    if ($table === null || $classId < 1) {
        attendanceJsonError(422, 'Choose a valid class and class type.');
    }

    $role = attendanceRequireStaff();
    $classStmt = $pdo->prepare("SELECT id, name, is_active FROM {$table} WHERE id = ? LIMIT 1");
    $classStmt->execute([$classId]);
    $class = $classStmt->fetch(PDO::FETCH_ASSOC);

    if (!$class || (int) $class['is_active'] !== 1) {
        attendanceJsonError(404, 'The selected class was not found or is inactive.');
    }

    if ($classType === 'conventional' && attendanceHasArabicClassWithName($pdo, (string) $class['name'])) {
        attendanceJsonError(404, 'The selected class belongs to the Arabic category.');
    }

    if ($role === 'teacher') {
        $assignment = $pdo->prepare(
            'SELECT 1
             FROM teacher_assignments
             WHERE account_id = ?
               AND class_type = ?
               AND class_id = ?
               AND is_active = 1
             LIMIT 1'
        );
        $assignment->execute([(int) ($_SESSION['admin_id'] ?? 0), $classType, $classId]);

        if (!$assignment->fetchColumn()) {
            attendanceJsonError(403, 'You are not assigned to this class.');
        }
    }

    return $class;
}

/**
 * JSON POST requests must include a same-origin custom header. Cross-origin
 * HTML forms cannot set it, which provides CSRF protection without new tables.
 */
function attendanceRequireSafePost(): void
{
    if (($_SERVER['HTTP_X_MARKAZ_REQUEST'] ?? '') !== 'attendance') {
        attendanceJsonError(403, 'Please reload the attendance page and try again.');
    }

    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');

    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $requestHost = preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (!is_string($originHost) || strcasecmp($originHost, $requestHost) !== 0) {
            attendanceJsonError(403, 'Cross-origin attendance requests are not allowed.');
        }
    }
}
