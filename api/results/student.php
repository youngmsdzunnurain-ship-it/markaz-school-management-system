<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/ss-curriculum.php';
require_once __DIR__ . '/../../app/helpers/student-portal-access.php';
require_once __DIR__ . '/../../app/helpers/arabic-class-names.php';

$studentPortalScope = studentPortalResultScope();
if ($studentPortalScope === null) {
    requireResultStaff();
}

if ($studentPortalScope !== null) {
    if (($_GET['print'] ?? '') === '1' && !systemAccessEnabled('student_print_results')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Student printing is currently disabled by the Admin.']);
        exit;
    }
}

// Staff printing and export each require their own independently assigned permission.
// A verified student portal session is read-only and cannot enter staff actions.
if ($studentPortalScope === null) {
    if (($_GET['print'] ?? '') === '1') {
        requireRolePermission('print_results');
    }
}
header('Content-Type: application/json; charset=utf-8');

$classType = (string) ($_GET['class_type'] ?? 'conventional');
if (!in_array($classType, ['conventional', 'arabic'], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid class type.'
    ]);
    exit;
}

$requestedClassId = filter_input(
    INPUT_GET,
    'class_id',
    FILTER_VALIDATE_INT
);

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
            COALESCE(NULLIF(st.arabic_class_id, 0), st.arabia_class_id) AS arabic_class_id,
            st.memorization_portion_override,

            c.name AS class_name,
            c.level_group,

            ac.name AS arabic_class,
            acm.memorization_portion AS default_memorization_portion

        FROM students st

        LEFT JOIN classes c
            ON c.id = st.class_id

        LEFT JOIN arabic_classes ac
            ON ac.id = COALESCE(NULLIF(st.arabic_class_id, 0), st.arabia_class_id)

        LEFT JOIN arabic_class_memorization_defaults acm
            ON acm.arabic_class_id = COALESCE(NULLIF(st.arabic_class_id, 0), st.arabia_class_id)

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
    $classId = $studentPortalScope !== null
        ? (int) $studentPortalScope['class_id']
        : ($classType === 'arabic'
            ? (int) ($student['arabic_class_id'] ?? 0)
            : (int) ($student['class_id'] ?? 0));

    if ($classType === 'arabic' && $studentPortalScope === null) {
        $student['class_name'] = markazCanonicalArabicClassName((string) ($student['arabic_class'] ?? $student['arabic_class_name'] ?? ''));
        $student['arabic_class'] = $student['class_name'];
    }

    if ($studentPortalScope !== null) {
        $portalClassStmt = $pdo->prepare(
            "SELECT id, name FROM classes
             WHERE id = ? AND COALESCE(level_group, '') <> 'Quranic'
             LIMIT 1"
        );
        $portalClassStmt->execute([$classId]);
        $portalClass = $portalClassStmt->fetch();
        if (!$portalClass) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'The selected student class is not valid.']);
            exit;
        }
        $student['class_id'] = $classId;
        $student['class_name'] = $portalClass['name'];
    }

    if ($classId < 1) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => $classType === 'arabic'
                ? 'Student is not assigned to an Arabic class.'
                : 'Student is not assigned to a conventional class.'
        ]);
        exit;
    }

    if ($classType === 'conventional') {
        ensureSeniorSecondaryCurriculum($pdo, $classId);
    }

    if ($requestedClassId !== null
        && $requestedClassId !== false
        && (int) $requestedClassId !== $classId) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Student does not belong to the requested class.'
        ]);
        exit;
    }

    $isTeacher = $studentPortalScope === null && currentAdminRole() === 'teacher';
    $teacherAccountId = (int) ($_SESSION['admin_id'] ?? 0);
    $teacherHasFullClassAccess = false;
    if ($isTeacher) {
        $assignmentCheck = $pdo->prepare(
            'SELECT subject_id FROM teacher_assignments
             WHERE account_id = ? AND class_type = ? AND class_id = ? AND is_active = 1'
        );
        $assignmentCheck->execute([$teacherAccountId, $classType, $classId]);
        $teacherAssignmentSubjects = $assignmentCheck->fetchAll(PDO::FETCH_COLUMN);
        $teacherHasFullClassAccess = in_array(null, $teacherAssignmentSubjects, true);
        if (!$teacherAssignmentSubjects) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not assigned to this class.']);
            exit;
        }
    }

    if ($studentPortalScope !== null) {
        $periodStmt = $pdo->prepare(
            'SELECT ap.id, s.name AS session_name, t.name AS term_name,
                    ap.examination_date, ap.vacation_date, ap.resumption_date
             FROM academic_periods ap
             INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
             INNER JOIN terms t ON t.id = ap.term_id
             WHERE ap.id = ? LIMIT 1'
        );
        $periodStmt->execute([(int) $studentPortalScope['academic_period_id']]);
    } else {
        $periodStmt = $pdo->query(
            'SELECT ap.id, s.name AS session_name, t.name AS term_name,
                    ap.examination_date, ap.vacation_date, ap.resumption_date
             FROM academic_periods ap
             INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
             INNER JOIN terms t ON t.id = ap.term_id
             WHERE s.is_current = 1 AND ap.is_current = 1
             ORDER BY ap.id DESC LIMIT 1'
        );
    }

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
    $mappingTable = $classType === 'arabic'
        ? 'arabic_class_subjects'
        : 'class_subjects';
    $mappingClassColumn = $classType === 'arabic'
        ? 'arabic_class_id'
        : 'class_id';

    $teacherSubjectScopeSql = $isTeacher
        ? " AND EXISTS (
              SELECT 1 FROM teacher_assignments ta
              WHERE ta.account_id = ?
                AND ta.class_type = ?
                AND ta.class_id = ?
                AND ta.is_active = 1
                AND (ta.subject_id = s.id OR ta.subject_id IS NULL)
          )"
        : '';
    $teacherSubjectScopeParams = $isTeacher
        ? [$teacherAccountId, $classType, $classId]
        : [];

    $subjectStmt = $pdo->prepare(
        "SELECT
            s.id,
            s.name,
            s.code,
            s.max_ca1,
            s.max_ca2,
            s.max_exam,
            cs.is_required,
            cs.sort_order
        FROM {$mappingTable} cs
        INNER JOIN subjects s
            ON s.id = cs.subject_id
        WHERE cs.{$mappingClassColumn} = ?
          AND s.is_active = 1
          {$teacherSubjectScopeSql}
        ORDER BY
            cs.sort_order,
            s.name"
    );

    $subjectStmt->execute(array_merge([$classId], $teacherSubjectScopeParams));

    $subjects = $subjectStmt->fetchAll();

    /*
     * STUDENT RESULTS
     */
    $teacherResultSubjectScopeSql = $isTeacher
        ? " AND EXISTS (
              SELECT 1 FROM teacher_assignments ta
              WHERE ta.account_id = ?
                AND ta.class_type = ?
                AND ta.class_id = ?
                AND ta.is_active = 1
                AND (ta.subject_id = results.subject_id OR ta.subject_id IS NULL)
          )"
        : '';
    $teacherResultSubjectScopeParams = $isTeacher
        ? [$teacherAccountId, $classType, $classId]
        : [];

    $resultStmt = $pdo->prepare(
        "SELECT
            id,
            subject_id,
            ca1,
            ca2,
            exam,
            teacher_remark,
            result_remark
        FROM results
        WHERE student_id = ?
          AND class_type = ?
          AND class_id = ?
          AND academic_period_id = ?
          AND subject_id IN (
              SELECT cs.subject_id
              FROM {$mappingTable} cs
              INNER JOIN subjects current_subject_record
                  ON current_subject_record.id = cs.subject_id
                 AND current_subject_record.is_active = 1
              WHERE cs.{$mappingClassColumn} = ?
          )
          {$teacherResultSubjectScopeSql}"
    );

    $resultStmt->execute([
        $studentId,
        $classType,
        $classId,
        $period['id'],
        $classId,
        ...$teacherResultSubjectScopeParams
    ]);

    $resultRows = $resultStmt->fetchAll();

    /*
     * SERVER-SIDE RESULT COMPLETION AND PRINT ELIGIBILITY
     * Blank/missing values are incomplete; a saved zero is complete.
     */
    $behaviourFields = [
        'attendance' => 'Punctuality',
        'neatness' => 'Neatness',
        'contentment' => 'Contentment',
        'obedience' => 'Obedience',
        'responsiveness' => 'Responsiveness',
        'calmness' => 'Calmness',
        'tolerance' => 'Tolerance',
        'attentiveness' => 'Attentiveness',
        'truthfulness' => 'Truthfulness',
    ];

    $scoreRowsBySubject = [];
    foreach ($resultRows as $savedRow) {
        $scoreRowsBySubject[(int) $savedRow['subject_id']] = $savedRow;
    }

    $requiredCount = 0;
    $completedCount = 0;
    $missingFields = [];

    foreach ($subjects as $subject) {
        if (!in_array($subject['is_required'], [true, 1, '1'], true)) {
            continue;
        }

        $subjectId = (int) $subject['id'];
        $saved = $scoreRowsBySubject[$subjectId] ?? null;

        foreach (['ca1' => 'CA1', 'ca2' => 'CA2', 'exam' => 'Exam'] as $field => $label) {
            $requiredCount++;
            $value = $saved[$field] ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                $completedCount++;
            } else {
                $missingFields[] = (string) $subject['name'] . ' — ' . $label;
            }
        }
    }

    $behaviourStmt = $pdo->prepare(
        'SELECT attendance, neatness, contentment, obedience, responsiveness,
                calmness, tolerance, attentiveness, truthfulness
         FROM behavioural_reports
         WHERE student_id = ? AND academic_period_id = ?
         LIMIT 1'
    );
    $behaviourStmt->execute([$studentId, $period['id']]);
    $savedBehaviour = $behaviourStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    foreach ($behaviourFields as $field => $label) {
        $requiredCount++;
        $value = $savedBehaviour[$field] ?? null;

        if ($value !== null && trim((string) $value) !== '') {
            $completedCount++;
        } else {
            $missingFields[] = $label;
        }
    }

    $completionPercent = $requiredCount > 0
        ? round(($completedCount / $requiredCount) * 100, 2)
        : 0.0;

    $thresholdStmt = $pdo->prepare(
        'SELECT setting_value FROM settings
         WHERE setting_key = ?
         LIMIT 1'
    );
    $thresholdStmt->execute(['result_print_completion_threshold']);
    $savedThreshold = $thresholdStmt->fetchColumn();

    $printThreshold = $savedThreshold !== false && is_numeric($savedThreshold)
        ? max(0.0, min(100.0, (float) $savedThreshold))
        : 95.0;

    $completionSummary = [
        'percentage' => $completionPercent,
        'completed_fields' => $completedCount,
        'required_fields' => $requiredCount,
        'threshold' => $printThreshold,
        'eligible' => $completionPercent >= $printThreshold,
        'missing_fields' => $missingFields,
    ];

    if (
        (($_GET['print'] ?? '') === '1' || ($_GET['export'] ?? '') === '1')
        && $completionPercent < $printThreshold
    ) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'code' => 'RESULT_INCOMPLETE',
            'message' => 'This result is ' . $completionPercent
                . '% complete. Printing requires at least '
                . $printThreshold . '% completion.',
            'completion' => $completionSummary,
        ]);
        exit;
    }


    $resultMap = [];

    foreach ($resultRows as $row) {
        $subjectId = (int) $row['subject_id'];

        $resultMap[$subjectId] = [
            'id' => (int) $row['id'],
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

    /*
     * CLASS AVERAGES
     */
    $teacherResultScopeSql = $isTeacher
        ? " AND EXISTS (
              SELECT 1 FROM teacher_assignments ta
              WHERE ta.account_id = ?
                AND ta.class_type = ?
                AND ta.class_id = ?
                AND ta.is_active = 1
                AND (ta.subject_id = r.subject_id OR ta.subject_id IS NULL)
          )"
        : '';
    $teacherResultScopeParams = $isTeacher
        ? [$teacherAccountId, $classType, $classId]
        : [];

    $classAverageStmt = $pdo->prepare(
        "SELECT
            st.id AS student_id,
            COALESCE(
                SUM(
                    COALESCE(r.ca1, 0)
                    + COALESCE(r.ca2, 0)
                    + COALESCE(r.exam, 0)
                ),
                0
            ) AS total_score,
            COUNT(r.id) AS completed_subjects
        FROM students st
        LEFT JOIN results r
            ON r.student_id = st.id
           AND r.class_type = ?
           AND r.class_id = ?
           AND r.academic_period_id = ?
           AND EXISTS (
               SELECT 1
               FROM {$mappingTable} current_subject
               INNER JOIN subjects active_subject
                   ON active_subject.id = current_subject.subject_id
                  AND active_subject.is_active = 1
               WHERE current_subject.{$mappingClassColumn} = ?
                 AND current_subject.subject_id = r.subject_id
           )
           {$teacherResultScopeSql}
        WHERE st.{$mappingClassColumn} = ?
          AND st.is_active = 1
        GROUP BY st.id"
    );

    $classAverageStmt->execute([
        $classType,
        $classId,
        $period['id'],
        $classId,
        $classId,
        ...$teacherResultScopeParams
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
        'current_user_role' => $studentPortalScope === null ? currentAdminRole() : null,
        'student_document_permissions' => $studentPortalScope !== null ? [
            'can_print_results' => systemAccessEnabled('student_print_results'),
        ] : null,

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

            'profile_image' =>
                $student['profile_image'],

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
                $effectiveMemorization,

            'teacher_full_class_access' => $isTeacher && $teacherHasFullClassAccess
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
        'completion' => $completionSummary,
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
