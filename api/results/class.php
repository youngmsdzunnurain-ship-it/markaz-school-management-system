<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/config/app.php';

requireResultStaff();

header('Content-Type: application/json; charset=utf-8');

$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

if (!$classId || $classId < 1) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'A valid class ID is required.'
    ]);

    exit;
}

try {
    $pdo = db();

    $classStmt = $pdo->prepare(
        'SELECT id, name, level_group, is_active
         FROM classes
         WHERE id = ?
         LIMIT 1'
    );

    $classStmt->execute([$classId]);
    $class = $classStmt->fetch();

    if (!$class) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Class not found.'
        ]);

        exit;
    }

    $periodStmt = $pdo->query(
        'SELECT
            ap.id,
            s.name AS session_name,
            t.name AS term_name,
            ap.examination_date,
            ap.vacation_date,
            ap.resumption_date
         FROM academic_periods ap
         INNER JOIN academic_sessions s
            ON s.id = ap.academic_session_id
         INNER JOIN terms t
            ON t.id = ap.term_id
         WHERE s.is_current = 1
         ORDER BY ap.id DESC
         LIMIT 1'
    );

    $period = $periodStmt->fetch();

    if (!$period) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'No current academic period is configured.'
        ]);

        exit;
    }

    $subjectStmt = $pdo->prepare(
        'SELECT
            s.id,
            s.name,
            s.code,
            s.max_ca1,
            s.max_ca2,
            s.max_exam,
            cs.is_required,
            cs.sort_order
         FROM class_subjects cs
         INNER JOIN subjects s
            ON s.id = cs.subject_id
         WHERE cs.class_id = ?
           AND s.is_active = 1
         ORDER BY cs.sort_order, s.name'
    );

    $subjectStmt->execute([$classId]);
    $subjects = $subjectStmt->fetchAll();

    $studentStmt = $pdo->prepare(
        'SELECT
            id,
            admission_number,
            first_name,
            middle_name,
            last_name,
            gender,
            date_of_birth,
            is_active
         FROM students
         WHERE class_id = ?
           AND is_active = 1
         ORDER BY first_name, middle_name, last_name, admission_number'
    );

    $studentStmt->execute([$classId]);
    $students = $studentStmt->fetchAll();

    $resultStmt = $pdo->prepare(
        'SELECT
            r.id,
            r.student_id,
            r.subject_id,
            r.ca1,
            r.ca2,
            r.exam,
            r.teacher_remark,
            r.result_remark
         FROM results r
         INNER JOIN students st
            ON st.id = r.student_id
         WHERE st.class_id = ?
           AND r.academic_period_id = ?'
    );

    $resultStmt->execute([
        $classId,
        $period['id']
    ]);

    $resultRows = $resultStmt->fetchAll();

    $results = [];

    foreach ($resultRows as $row) {
        $results[] = [
            'id' => (int) $row['id'],
            'student_id' => (int) $row['student_id'],
            'subject_id' => (int) $row['subject_id'],
            'ca1' => (float) $row['ca1'],
            'ca2' => (float) $row['ca2'],
            'exam' => (float) $row['exam'],
            'total' => round(
                (float) $row['ca1']
                + (float) $row['ca2']
                + (float) $row['exam'],
                2
            ),
            'teacher_remark' => $row['teacher_remark'],
            'result_remark' => $row['result_remark']
        ];
    }

    echo json_encode([
        'success' => true,
        'class' => [
            'id' => (int) $class['id'],
            'name' => $class['name'],
            'level_group' => $class['level_group'],
            'is_active' => (bool) $class['is_active']
        ],
        'academic_period' => [
            'id' => (int) $period['id'],
            'session_name' => $period['session_name'],
            'term_name' => $period['term_name'],
            'examination_date' => $period['examination_date'],
            'vacation_date' => $period['vacation_date'],
            'resumption_date' => $period['resumption_date']
        ],
        'subjects' => array_map(
            static function (array $subject): array {
                return [
                    'id' => (int) $subject['id'],
                    'name' => $subject['name'],
                    'code' => $subject['code'],
                    'max_ca1' => (float) $subject['max_ca1'],
                    'max_ca2' => (float) $subject['max_ca2'],
                    'max_exam' => (float) $subject['max_exam'],
                    'is_required' => (bool) $subject['is_required'],
                    'sort_order' => (int) $subject['sort_order']
                ];
            },
            $subjects
        ),
        'students' => array_map(
            static function (array $student): array {
                return [
                    'id' => (int) $student['id'],
                    'admission_number' => $student['admission_number'],
                    'first_name' => $student['first_name'],
                    'middle_name' => $student['middle_name'],
                    'last_name' => $student['last_name'],
                    'gender' => $student['gender'],
                    'date_of_birth' => $student['date_of_birth'],
                    'is_active' => (bool) $student['is_active']
                ];
            },
            $students
        ),
        'results' => $results
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load class results.'
    ]);
}
