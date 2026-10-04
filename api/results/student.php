<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/config/app.php';

requireResultStaff();

header('Content-Type: application/json; charset=utf-8');

$studentId = filter_input(
    INPUT_GET,
    'student_id',
    FILTER_VALIDATE_INT
);

if (!$studentId || $studentId < 1) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'A valid student ID is required.'
    ]);

    exit;
}

try {
    $pdo = db();

    /*
     * STUDENT
     * Arabic class and memorization are separate from
     * the conventional class configuration.
     */
    $studentStmt = $pdo->prepare(
        'SELECT
            st.id,
            st.admission_number,
            st.first_name,
            st.middle_name,
            st.last_name,
            st.gender,
            st.student_type,
            st.date_of_birth,
            st.is_active,
            st.profile_image,
            st.class_id,
            st.arabic_class_id,
            st.memorization_portion_override,

            c.name AS class_name,
            c.level_group,

            ac.name AS arabic_class,
            acm.memorization_portion AS default_memorization_portion

        FROM students st

        INNER JOIN classes c
            ON c.id = st.class_id

        LEFT JOIN arabic_classes ac
            ON ac.id = st.arabic_class_id

        LEFT JOIN arabic_class_memorization_defaults acm
            ON acm.arabic_class_id = st.arabic_class_id

        WHERE st.id = ?

        LIMIT 1'
    );

    $studentStmt->execute([$studentId]);

    $student = $studentStmt->fetch();

    if (!$student) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Student not found.'
        ]);

        exit;
    }

    /*
     * CURRENT ACADEMIC PERIOD
     */
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

    /*
     * CLASS SUBJECTS
     */
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

        ORDER BY
            cs.sort_order,
            s.name'
    );

    $subjectStmt->execute([
        $student['class_id']
    ]);

    $subjects = $subjectStmt->fetchAll();

    /*
     * STUDENT RESULTS
     */
    $resultStmt = $pdo->prepare(
        'SELECT
            id,
            subject_id,
            ca1,
            ca2,
            exam,
            teacher_remark,
            result_remark

        FROM results

        WHERE student_id = ?
          AND academic_period_id = ?'
    );

    $resultStmt->execute([
        $studentId,
        $period['id']
    ]);

    $resultRows = $resultStmt->fetchAll();

    $resultMap = [];

    foreach ($resultRows as $row) {
        $subjectId = (int) $row['subject_id'];

        $resultMap[$subjectId] = [
            'id' => (int) $row['id'],
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

    /*
     * CLASS AVERAGES
     */
    $classAverageStmt = $pdo->prepare(
        'SELECT
            st.id AS student_id,
            COALESCE(
                SUM(r.ca1 + r.ca2 + r.exam),
                0
            ) AS total_score,
            COUNT(r.id) AS completed_subjects

        FROM students st

        LEFT JOIN results r
            ON r.student_id = st.id
           AND r.academic_period_id = ?

        WHERE st.class_id = ?
          AND st.is_active = 1

        GROUP BY st.id'
    );

    $classAverageStmt->execute([
        $period['id'],
        $student['class_id']
    ]);

    $classAverageRows =
        $classAverageStmt->fetchAll();

    $classAverages = [];

    foreach ($classAverageRows as $row) {
        $completedSubjects =
            (int) $row['completed_subjects'];

        if ($completedSubjects < 1) {
            continue;
        }

        $classAverages[] = round(
            (float) $row['total_score']
            / $completedSubjects,
            2
        );
    }

    /*
     * STUDENT AVERAGE
     */
    $studentTotalScore = 0;
    $studentCompletedSubjects = 0;

    foreach ($resultMap as $result) {
        $studentTotalScore +=
            (float) $result['total'];

        $studentCompletedSubjects++;
    }

    $studentAverage =
        $studentCompletedSubjects > 0
            ? round(
                $studentTotalScore
                / $studentCompletedSubjects,
                2
            )
            : null;

    $highestClassAverage =
        !empty($classAverages)
            ? max($classAverages)
            : null;

    $lowestClassAverage =
        !empty($classAverages)
            ? min($classAverages)
            : null;

    /*
     * EFFECTIVE MEMORIZATION PORTION
     *
     * Student override wins.
     * Otherwise Arabic Class default is used.
     */
    $memorizationOverride =
        $student['memorization_portion_override'];

    $memorizationDefault =
        $student['default_memorization_portion'];

    $effectiveMemorization =
        !empty($memorizationOverride)
            ? $memorizationOverride
            : $memorizationDefault;

    /*
     * ARABIC CLASS STUDENT COUNT
     *
     * Count active students using the same Arabic class.
     * This is intentionally independent of the conventional class.
     */
    $arabicClassStudentCount = 0;

    if (!empty($student['arabic_class_id'])) {
        $arabicClassCountStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM students
             WHERE arabic_class_id = ?
               AND is_active = 1'
        );

        $arabicClassCountStmt->execute([
            $student['arabic_class_id']
        ]);

        $arabicClassStudentCount = (int) $arabicClassCountStmt->fetchColumn();
    }

    /*
     * RESPONSE
     */
    echo json_encode([
        'success' => true,

        'student' => [
            'id' => (int) $student['id'],

            'admission_number' =>
                $student['admission_number'],

            'first_name' =>
                $student['first_name'],

            'middle_name' =>
                $student['middle_name'],

            'last_name' =>
                $student['last_name'],

            'gender' =>
                $student['gender'],

            'student_type' =>
                $student['student_type'],

            'date_of_birth' =>
                $student['date_of_birth'],

            'is_active' =>
                (int) $student['is_active'],

            'class_id' =>
                (int) $student['class_id'],

            'class_name' =>
                $student['class_name'],

            'level_group' =>
                $student['level_group'],

            'arabic_class_id' =>
                $student['arabic_class_id']
                    ? (int) $student['arabic_class_id']
                    : null,

            'arabic_class' =>
                $student['arabic_class'],
                'arabic_class_student_count' => $arabicClassStudentCount,

            'memorization_portion_override' =>
                $memorizationOverride,

            'default_memorization_portion' =>
                $memorizationDefault,

            'memorization_portion' =>
                $effectiveMemorization
        ],

        'academic_period' => [
            'id' =>
                (int) $period['id'],

            'session_name' =>
                $period['session_name'],

            'term_name' =>
                $period['term_name'],

            'examination_date' =>
                $period['examination_date'],

            'vacation_date' =>
                $period['vacation_date'],

            'resumption_date' =>
                $period['resumption_date']
        ],

        'performance' => [
            'student_average' =>
                $studentAverage,

            'highest_class_average' =>
                $highestClassAverage,

            'lowest_class_average' =>
                $lowestClassAverage
        ],

        /*
         * Keep BOTH structures so the existing frontend
         * can use whichever it expects.
         */
        'results' => $resultMap,

        'subjects' => array_map(
            static function (array $subject) use ($resultMap): array {
                $subjectId =
                    (int) $subject['id'];

                return [
                    'id' => $subjectId,

                    'name' =>
                        $subject['name'],

                    'code' =>
                        $subject['code'],

                    'max_ca1' =>
                        (float) $subject['max_ca1'],

                    'max_ca2' =>
                        (float) $subject['max_ca2'],

                    'max_exam' =>
                        (float) $subject['max_exam'],

                    'is_required' =>
                        (bool) $subject['is_required'],

                    'sort_order' =>
                        (int) $subject['sort_order'],

                    'result' =>
                        $resultMap[$subjectId] ?? null
                ];
            },
            $subjects
        )
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' =>
            'Unable to load student results.'
    ]);
}
