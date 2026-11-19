<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/arabic-class-names.php';
require_once __DIR__ . '/../../app/helpers/ss-curriculum.php';

requireResultStaff();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');

$classType = (string) ($_GET['class_type'] ?? '');

if (!in_array($classType, ['conventional', 'arabic'], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid class type.'
    ]);
    exit;
}

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
    $isTeacher = currentAdminRole() === 'teacher';
    $accountId = (int) ($_SESSION['admin_id'] ?? 0);

    if ($isTeacher) {
        $assignmentStmt = $pdo->prepare(
            'SELECT 1
             FROM teacher_assignments
             WHERE account_id = ?
               AND class_type = ?
               AND class_id = ?
               AND is_active = 1
             LIMIT 1'
        );
        $assignmentStmt->execute([$accountId, $classType, $classId]);

        if (!$assignmentStmt->fetchColumn()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'You are not assigned to this class.'
            ]);
            exit;
        }
    }

    $classSql = $classType === 'arabic'
        ? 'SELECT id, name, NULL AS level_group, is_active
           FROM arabic_classes WHERE id = ? LIMIT 1'
        : 'SELECT id, name, level_group, is_active
           FROM classes WHERE id = ? LIMIT 1';

    $classStmt = $pdo->prepare($classSql);

    $classStmt->execute([$classId]);
    $class = $classStmt->fetch();
    if ($class && $classType === 'arabic') {
        $class['name'] = markazCanonicalArabicClassName((string) $class['name']);
    }

    if (!$class) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Class not found.'
        ]);

        exit;
    }

    if ($classType === 'conventional') {
        ensureSeniorSecondaryCurriculum($pdo, (int) $classId);
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
           AND ap.is_current = 1
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

    $mappingTable = $classType === 'arabic'
        ? 'arabic_class_subjects'
        : 'class_subjects';
    $mappingClassColumn = $classType === 'arabic'
        ? 'arabic_class_id'
        : 'class_id';
    $studentClassExpression = $classType === 'arabic'
        ? 'COALESCE(NULLIF(st.arabic_class_id, 0), st.arabia_class_id)'
        : 'st.class_id';

    $subjectSql = "
        SELECT
            s.id,
            s.name,
            s.code,
            s.max_ca1,
            s.max_ca2,
            s.max_exam,
            cs.is_required,
            cs.sort_order
        FROM {$mappingTable} cs
        INNER JOIN subjects s ON s.id = cs.subject_id
        WHERE cs.{$mappingClassColumn} = ?
          AND s.is_active = 1";

    $subjectParams = [$classId];

    if ($isTeacher) {
        $subjectSql .= "
          AND EXISTS (
              SELECT 1
              FROM teacher_assignments ta
              WHERE ta.account_id = ?
                AND ta.class_type = ?
                AND ta.class_id = ?
                AND ta.is_active = 1
                AND (ta.subject_id = s.id OR ta.subject_id IS NULL)
          )";
        $subjectParams[] = $accountId;
        $subjectParams[] = $classType;
        $subjectParams[] = $classId;
    }

    $subjectSql .= ' ORDER BY cs.sort_order, s.name';
    $subjectStmt = $pdo->prepare($subjectSql);
    $subjectStmt->execute($subjectParams);
    $subjects = $subjectStmt->fetchAll();

    $studentStmt = $pdo->prepare(
        "SELECT
            st.id,
            st.admission_number,
            st.first_name,
            st.middle_name,
            st.last_name,
            st.gender,
            st.date_of_birth,
            st.is_active,
            COALESCE(NULLIF(st.arabic_class_id, 0), st.arabia_class_id) AS arabic_class_id,
            ac.name AS arabic_class_name
         FROM students st
         LEFT JOIN arabic_classes ac
            ON ac.id = COALESCE(NULLIF(st.arabic_class_id, 0), st.arabia_class_id)
         WHERE {$studentClassExpression} = ?
           AND st.is_active = 1
         ORDER BY st.first_name, st.middle_name, st.last_name, st.admission_number"
    );

    $studentStmt->execute([$classId]);
    $students = $studentStmt->fetchAll();
    if ($classType === 'arabic') {
        foreach ($students as &$student) {
            $student['arabic_class_name'] = markazCanonicalArabicClassName((string) ($student['arabic_class_name'] ?? ''));
        }
        unset($student);
    }

    $resultSql = "
        SELECT
            r.id,
            r.student_id,
            r.subject_id,
            r.ca1,
            r.ca2,
            r.exam,
            r.teacher_remark,
            r.result_remark
        FROM results r
        INNER JOIN students st ON st.id = r.student_id
        WHERE {$studentClassExpression} = ?
          AND r.class_type = ?
          AND r.class_id = ?
          AND r.academic_period_id = ?
          AND r.subject_id IN (
              SELECT mapped_subject.subject_id
              FROM {$mappingTable} mapped_subject
              WHERE mapped_subject.{$mappingClassColumn} = ?
          )";

    $resultParams = [
        $classId,
        $classType,
        $classId,
        $period['id'],
        $classId
    ];

    if ($isTeacher) {
        $resultSql .= "
          AND EXISTS (
              SELECT 1
              FROM teacher_assignments ta
              WHERE ta.account_id = ?
                AND ta.class_type = ?
                AND ta.class_id = ?
                AND ta.is_active = 1
                AND (ta.subject_id = r.subject_id OR ta.subject_id IS NULL)
          )";
        $resultParams[] = $accountId;
        $resultParams[] = $classType;
        $resultParams[] = $classId;
    }

    $resultStmt = $pdo->prepare($resultSql);
    $resultStmt->execute($resultParams);
    $resultRows = $resultStmt->fetchAll();

    $results = [];

    foreach ($resultRows as $row) {
        $results[] = [
            'id' => (int) $row['id'],
            'student_id' => (int) $row['student_id'],
            'subject_id' => (int) $row['subject_id'],
            'ca1' => $row['ca1'] === null ? null : (float) $row['ca1'],
            'ca2' => $row['ca2'] === null ? null : (float) $row['ca2'],
            'exam' => $row['exam'] === null ? null : (float) $row['exam'],
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
                    'is_active' => (bool) $student['is_active'],
                    'arabic_class_id' => $student['arabic_class_id'] !== null
                        ? (int) $student['arabic_class_id']
                        : null,
                    'arabic_class_name' => $student['arabic_class_name']
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
