<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/config/app.php';

requireResultStaff();
header('Content-Type: application/json; charset=utf-8');

$classType = (string) ($_GET['class_type'] ?? 'conventional');
if (!in_array($classType, ['conventional', 'arabic'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid class type.']);
    exit;
}

$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
if (!$classId || $classId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid class ID is required.']);
    exit;
}

try {
    $pdo = db();
    $isTeacher = currentAdminRole() === 'teacher';
    $accountId = (int) ($_SESSION['admin_id'] ?? 0);

    if ($isTeacher) {
        $assignment = $pdo->prepare(
            'SELECT 1 FROM teacher_assignments
             WHERE account_id = ? AND class_type = ? AND class_id = ?
               AND is_active = 1 LIMIT 1'
        );
        $assignment->execute([$accountId, $classType, $classId]);
        if (!$assignment->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not assigned to this class.']);
            exit;
        }
    }

    $classSql = $classType === 'arabic'
        ? 'SELECT id, name, NULL AS level_group, is_active FROM arabic_classes WHERE id = ? LIMIT 1'
        : 'SELECT id, name, level_group, is_active FROM classes WHERE id = ? LIMIT 1';
    $classStmt = $pdo->prepare($classSql);
    $classStmt->execute([$classId]);
    $class = $classStmt->fetch();

    if (!$class) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Class not found.']);
        exit;
    }

    // Subject choices do not depend on a configured academic period.
    // Load them even when the period is missing so the subject dropdown
    // never appears empty solely because period configuration is incomplete.
    $periodStmt = $pdo->query(
        'SELECT ap.id, s.name AS session_name, t.name AS term_name,
                ap.examination_date, ap.vacation_date, ap.resumption_date
         FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
         INNER JOIN terms t ON t.id = ap.term_id
         WHERE s.is_current = 1
         ORDER BY ap.id DESC LIMIT 1'
    );
    $period = $periodStmt->fetch() ?: null;

    $mappingTable = $classType === 'arabic' ? 'arabic_class_subjects' : 'class_subjects';
    $mappingClassColumn = $classType === 'arabic' ? 'arabic_class_id' : 'class_id';
    $studentClassColumn = $classType === 'arabic' ? 'arabic_class_id' : 'class_id';

    $subjectSql = "SELECT s.id, s.name, s.code, s.max_ca1, s.max_ca2, s.max_exam,
                           cs.is_required, cs.sort_order
                    FROM {$mappingTable} cs
                    INNER JOIN subjects s ON s.id = cs.subject_id
                    WHERE cs.{$mappingClassColumn} = ? AND s.is_active = 1";
    $subjectParams = [$classId];

    if ($isTeacher) {
        $subjectSql .= ' AND EXISTS (
            SELECT 1 FROM teacher_assignments ta
            WHERE ta.account_id = ? AND ta.class_type = ? AND ta.class_id = ?
              AND ta.is_active = 1 AND (ta.subject_id = s.id OR ta.subject_id IS NULL)
        )';
        array_push($subjectParams, $accountId, $classType, $classId);
    }

    $subjectSql .= ' ORDER BY cs.sort_order, s.name';
    $subjectStmt = $pdo->prepare($subjectSql);
    $subjectStmt->execute($subjectParams);
    $subjects = array_map(static fn(array $subject): array => [
        'id' => (int) $subject['id'],
        'name' => $subject['name'],
        'code' => $subject['code'],
        'max_ca1' => (float) $subject['max_ca1'],
        'max_ca2' => (float) $subject['max_ca2'],
        'max_exam' => (float) $subject['max_exam'],
        'is_required' => (bool) $subject['is_required'],
        'sort_order' => (int) $subject['sort_order']
    ], $subjectStmt->fetchAll());

    $studentStmt = $pdo->prepare(
        "SELECT id, admission_number, first_name, middle_name, last_name,
                gender, date_of_birth, is_active
         FROM students WHERE {$studentClassColumn} = ? AND is_active = 1
         ORDER BY first_name, middle_name, last_name, admission_number"
    );
    $studentStmt->execute([$classId]);
    $students = array_map(static fn(array $student): array => [
        'id' => (int) $student['id'],
        'admission_number' => $student['admission_number'],
        'first_name' => $student['first_name'],
        'middle_name' => $student['middle_name'],
        'last_name' => $student['last_name'],
        'gender' => $student['gender'],
        'date_of_birth' => $student['date_of_birth'],
        'is_active' => (bool) $student['is_active']
    ], $studentStmt->fetchAll());

    $results = [];
    if ($period !== null) {
        $resultSql = "SELECT r.id, r.student_id, r.subject_id, r.ca1, r.ca2, r.exam,
                             r.teacher_remark, r.result_remark
                      FROM results r
                      INNER JOIN students st ON st.id = r.student_id
                      WHERE st.{$studentClassColumn} = ?
                        AND r.class_type = ? AND r.class_id = ?
                        AND r.academic_period_id = ?";
        $resultParams = [$classId, $classType, $classId, $period['id']];

        if ($isTeacher) {
            $resultSql .= ' AND EXISTS (
                SELECT 1 FROM teacher_assignments ta
                WHERE ta.account_id = ? AND ta.class_type = ? AND ta.class_id = ?
                  AND ta.is_active = 1 AND (ta.subject_id = r.subject_id OR ta.subject_id IS NULL)
            )';
            array_push($resultParams, $accountId, $classType, $classId);
        }

        $resultStmt = $pdo->prepare($resultSql);
        $resultStmt->execute($resultParams);
        foreach ($resultStmt->fetchAll() as $row) {
            $results[] = [
                'id' => (int) $row['id'],
                'student_id' => (int) $row['student_id'],
                'subject_id' => (int) $row['subject_id'],
                'ca1' => $row['ca1'] === null ? null : (float) $row['ca1'],
                'ca2' => $row['ca2'] === null ? null : (float) $row['ca2'],
                'exam' => $row['exam'] === null ? null : (float) $row['exam'],
                'total' => round((float) $row['ca1'] + (float) $row['ca2'] + (float) $row['exam'], 2),
                'teacher_remark' => $row['teacher_remark'],
                'result_remark' => $row['result_remark']
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => $period === null ? 'Subjects loaded. No current academic period is configured.' : null,
        'class' => [
            'id' => (int) $class['id'],
            'name' => $class['name'],
            'level_group' => $class['level_group'],
            'is_active' => (bool) $class['is_active']
        ],
        'academic_period' => $period === null ? null : [
            'id' => (int) $period['id'],
            'session_name' => $period['session_name'],
            'term_name' => $period['term_name'],
            'examination_date' => $period['examination_date'],
            'vacation_date' => $period['vacation_date'],
            'resumption_date' => $period['resumption_date']
        ],
        'subjects' => $subjects,
        'students' => $students,
        'results' => $results
    ]);
} catch (Throwable $e) {
    error_log('Class results load failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load class results. Please refresh and try again.']);
}
