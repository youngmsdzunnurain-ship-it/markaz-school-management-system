<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (currentAdminRole() === 'teacher') {
    require __DIR__ . '/teacher-stats.php';
    exit;
}

requireAdmin();

$pdo = db();

/*
 * BASIC DASHBOARD COUNTS
 */
$students = (int) $pdo->query(
    "SELECT COUNT(*) FROM students WHERE is_active = 1"
)->fetchColumn();

$classes = (int) $pdo->query(
    "SELECT COUNT(*) FROM classes WHERE is_active = 1"
)->fetchColumn();

$subjects = (int) $pdo->query(
    "SELECT COUNT(*) FROM subjects WHERE is_active = 1"
)->fetchColumn();

/*
 * CURRENT ACADEMIC PERIOD
 * Same rule used by the Results pages.
 */
$periodStmt = $pdo->query(
    "SELECT
        ap.id
     FROM academic_periods ap
     INNER JOIN academic_sessions s
        ON s.id = ap.academic_session_id
     INNER JOIN terms t
        ON t.id = ap.term_id
     WHERE s.is_current = 1
     ORDER BY ap.id DESC
     LIMIT 1"
);

$period = $periodStmt->fetch();

$results = 0;
$expectedResults = 0;
$completeStudents = 0;
$incompleteStudents = 0;
$noResultStudents = 0;
$completionPercentage = 0;

if ($period) {
    $periodId = (int) $period['id'];

    /*
     * Total result rows recorded for the current academic period.
     */
    $resultStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM results r
         INNER JOIN students st
            ON st.id = r.student_id
         WHERE st.is_active = 1
           AND r.academic_period_id = ?"
    );

    $resultStmt->execute([$periodId]);
    $results = (int) $resultStmt->fetchColumn();

    /*
     * Build student result-completion information.
     *
     * A student's expected results are based on the ACTIVE subjects
     * assigned to that student's conventional class.
     *
     * Only REQUIRED subjects are used for completion status.
     */
    $progressStmt = $pdo->prepare(
        "SELECT
            st.id AS student_id,
            COUNT(DISTINCT CASE
                WHEN cs.is_required = 1
                 AND s.is_active = 1
                THEN cs.subject_id
            END) AS required_subjects,
            COUNT(DISTINCT CASE
                WHEN cs.is_required = 1
                 AND s.is_active = 1
                 AND r.id IS NOT NULL
                THEN cs.subject_id
            END) AS completed_subjects
         FROM students st
         LEFT JOIN class_subjects cs
            ON cs.class_id = st.class_id
         LEFT JOIN subjects s
            ON s.id = cs.subject_id
         LEFT JOIN results r
            ON r.student_id = st.id
           AND r.subject_id = cs.subject_id
           AND r.academic_period_id = ?
         WHERE st.is_active = 1
         GROUP BY st.id"
    );

    $progressStmt->execute([$periodId]);
    $progressRows = $progressStmt->fetchAll();

    foreach ($progressRows as $row) {
        $required = (int) $row['required_subjects'];
        $completed = (int) $row['completed_subjects'];

        /*
         * If a class has no required subjects configured,
         * do not incorrectly mark the student as complete.
         */
        if ($required === 0 || $completed === 0) {
            $noResultStudents++;
        } elseif ($completed >= $required) {
            $completeStudents++;
        } else {
            $incompleteStudents++;
        }

        $expectedResults += $required;
    }

    if ($expectedResults > 0) {
        $completionPercentage = round(
            ($results / $expectedResults) * 100,
            1
        );

        if ($completionPercentage > 100) {
            $completionPercentage = 100;
        }
    }
}


  $classProgress = [];
  if ($period) {
      $classRows = $pdo->query(
          "SELECT id, name FROM classes WHERE is_active = 1 ORDER BY name"
      )->fetchAll();

      $behaviourTableExists = (bool) $pdo->query(
          "SHOW TABLES LIKE 'behavioural_reports'"
      )->fetchColumn();

      $scoreFields = ['ca1', 'ca2', 'exam'];
      $behaviourFields = [
          'attendance', 'neatness', 'contentment', 'obedience',
          'responsiveness', 'tolerance', 'attentiveness',
          'truthfulness', 'calmness'
      ];

      foreach ($classRows as $classRow) {
          $classId = (int) $classRow['id'];
          $studentStmt = $pdo->prepare(
              "SELECT id FROM students WHERE class_id = ? AND is_active = 1"
          );
          $studentStmt->execute([$classId]);
          $classStudentIds = array_map('intval', $studentStmt->fetchAll(PDO::FETCH_COLUMN));

          $requiredStmt = $pdo->prepare(
              "SELECT COUNT(*) FROM class_subjects cs
               INNER JOIN subjects s ON s.id = cs.subject_id AND s.is_active = 1
               WHERE cs.class_id = ? AND cs.is_required = 1"
          );
          $requiredStmt->execute([$classId]);
          $requiredSubjects = (int) $requiredStmt->fetchColumn();

          $expected = count($classStudentIds) * (($requiredSubjects * 3) + 9);
          $entered = 0;

          if ($classStudentIds && $requiredSubjects) {
              $scoreStmt = $pdo->prepare(
                  "SELECT
                      SUM(r.ca1 IS NOT NULL) AS ca1_count,
                      SUM(r.ca2 IS NOT NULL) AS ca2_count,
                      SUM(r.exam IS NOT NULL) AS exam_count
                   FROM students st
                   INNER JOIN class_subjects cs
                       ON cs.class_id = st.class_id AND cs.is_required = 1
                   INNER JOIN subjects s
                       ON s.id = cs.subject_id AND s.is_active = 1
                   LEFT JOIN results r
                       ON r.student_id = st.id
                       AND r.subject_id = cs.subject_id
                       AND r.academic_period_id = ?
                   WHERE st.class_id = ? AND st.is_active = 1"
              );
              $scoreStmt->execute([$periodId, $classId]);
              $scoreCounts = $scoreStmt->fetch() ?: [];
              foreach (['ca1_count', 'ca2_count', 'exam_count'] as $field) {
                  $entered += (int) ($scoreCounts[$field] ?? 0);
              }
          }

          if ($behaviourTableExists && $classStudentIds) {
              $behaviourStmt = $pdo->prepare(
                  "SELECT " . implode(', ', array_map(
                      fn($field) => "SUM(`$field` IS NOT NULL AND `$field` <> '') AS `$field`",
                      $behaviourFields
                  )) . "
                   FROM behavioural_reports br
                   INNER JOIN students st ON st.id = br.student_id
                   WHERE st.class_id = ? AND st.is_active = 1
                     AND br.academic_period_id = ?"
              );
              $behaviourStmt->execute([$classId, $periodId]);
              $behaviourCounts = $behaviourStmt->fetch() ?: [];
              foreach ($behaviourFields as $field) {
                  $entered += (int) ($behaviourCounts[$field] ?? 0);
              }
          }

          $classProgress[] = [
              'class_name' => $classRow['name'],
              'completion_percentage' => $expected
                  ? min(100, round($entered / $expected * 100, 1))
                  : 0
          ];
      }
  } else {
      // Keep active classes visible when no academic period is configured.
      $classNames = $pdo->query(
          "SELECT name FROM classes WHERE is_active = 1 ORDER BY name"
      )->fetchAll(PDO::FETCH_COLUMN);

      foreach ($classNames as $className) {
          $classProgress[] = [
              'class_name' => $className,
              'completion_percentage' => 0
          ];
      }
  }

echo json_encode([
    'success' => true,
    'stats' => [
        'students' => $students,
        'classes' => $classes,
        'subjects' => $subjects,
        'results' => $results,

        'complete_students' => $completeStudents,
        'incomplete_students' => $incompleteStudents,
        'no_result_students' => $noResultStudents,

        'expected_results' => $expectedResults,
        'completion_percentage' => $completionPercentage,
      'class_progress' => $classProgress,
    ],
]);
