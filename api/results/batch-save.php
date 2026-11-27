<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/helpers/markaz-ai.php';

requireResultStaff();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST request required.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    exit;
}

$classType = (string) ($input['class_type'] ?? '');
$classId = filter_var($input['class_id'] ?? null, FILTER_VALIDATE_INT);
$entries = $input['entries'] ?? null;

if (!in_array($classType, ['conventional', 'arabic'], true) || !$classId || !is_array($entries) || !$entries || count($entries) > 1000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid class and between 1 and 1,000 result rows are required.']);
    exit;
}

try {
    $pdo = db();
    $periodStmt = $pdo->query(
        'SELECT ap.id, ap.is_finalized
         FROM academic_periods ap
         INNER JOIN academic_sessions s ON s.id = ap.academic_session_id
         WHERE s.is_current = 1 AND ap.is_current = 1
         ORDER BY ap.id DESC LIMIT 1'
    );
    $period = $periodStmt->fetch();
    if (!$period) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No current academic period is configured.']);
        exit;
    }
    if ((int) $period['is_finalized'] === 1) {
        http_response_code(423);
        echo json_encode(['success' => false, 'code' => 'ACADEMIC_PERIOD_FINALIZED', 'message' => 'Results for this academic period are finalized and read-only.']);
        exit;
    }

    $studentClassExpression = $classType === 'arabic'
        ? 'COALESCE(NULLIF(arabic_class_id, 0), arabia_class_id)'
        : 'class_id';
    $mappingTable = $classType === 'arabic' ? 'arabic_class_subjects' : 'class_subjects';
    $mappingClassColumn = $classType === 'arabic' ? 'arabic_class_id' : 'class_id';

    $studentStmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND {$studentClassExpression} = ? AND is_active = 1 LIMIT 1");
    $subjectStmt = $pdo->prepare(
        "SELECT s.id, s.max_ca1, s.max_ca2, s.max_exam
         FROM {$mappingTable} cs
         INNER JOIN subjects s ON s.id = cs.subject_id
         WHERE cs.{$mappingClassColumn} = ? AND cs.subject_id = ? AND s.is_active = 1
         LIMIT 1"
    );
    $existingStmt = $pdo->prepare(
        'SELECT id, ca1, ca2, exam FROM results
         WHERE student_id = ? AND class_type = ? AND class_id = ? AND subject_id = ? AND academic_period_id = ?
         LIMIT 1'
    );

    $normalised = [];
    $seen = [];
    foreach ($entries as $index => $entry) {
        if (!is_array($entry)) {
            throw new InvalidArgumentException('Invalid result row at position ' . ($index + 1) . '.');
        }
        $studentId = filter_var($entry['student_id'] ?? null, FILTER_VALIDATE_INT);
        $subjectId = filter_var($entry['subject_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$studentId || !$subjectId) {
            throw new InvalidArgumentException('Every row must identify a student and subject.');
        }
        $key = $studentId . ':' . $subjectId;
        if (isset($seen[$key])) {
            throw new InvalidArgumentException('The batch contains a duplicate student/subject row.');
        }
        $seen[$key] = true;

        $scores = [];
        foreach (['ca1', 'ca2', 'exam'] as $field) {
            if (!isset($entry[$field]) || !is_numeric($entry[$field])) {
                throw new InvalidArgumentException(strtoupper($field) . ' must contain a valid score for every row.');
            }
            $scores[$field] = round((float) $entry[$field], 2);
            if ($scores[$field] < 0) {
                throw new InvalidArgumentException(strtoupper($field) . ' cannot be negative.');
            }
        }
        $studentStmt->execute([$studentId, $classId]);
        if (!$studentStmt->fetch()) {
            throw new InvalidArgumentException('A selected student does not belong to this active class.');
        }
        requireTeacherAssignment((int) $classId, (int) $subjectId, $classType);
        $subjectStmt->execute([$classId, $subjectId]);
        $subject = $subjectStmt->fetch();
        if (!$subject) {
            throw new InvalidArgumentException('A selected subject is not assigned to this class.');
        }
        foreach (['ca1' => 'max_ca1', 'ca2' => 'max_ca2', 'exam' => 'max_exam'] as $field => $maxKey) {
            if ($scores[$field] > (float) $subject[$maxKey]) {
                throw new InvalidArgumentException(strtoupper($field) . ' exceeds the allowed maximum of ' . $subject[$maxKey] . '.');
            }
        }
        $normalised[] = [
            'student_id' => (int) $studentId,
            'subject_id' => (int) $subjectId,
            'scores' => $scores,
            'teacher_remark' => trim((string) ($entry['teacher_remark'] ?? '')),
            'result_remark' => trim((string) ($entry['result_remark'] ?? ''))
        ];
    }

    $pdo->beginTransaction();
    $lockPeriod = $pdo->prepare('SELECT is_finalized FROM academic_periods WHERE id = ? FOR UPDATE');
    $lockPeriod->execute([(int) $period['id']]);
    $locked = $lockPeriod->fetch();
    if (!$locked || (int) $locked['is_finalized'] === 1) {
        throw new RuntimeException('The academic period was finalized before the batch could be saved. Nothing was saved.');
    }

    $insert = $pdo->prepare(
        'INSERT INTO results (student_id, class_type, class_id, subject_id, academic_period_id, ca1, ca2, exam, teacher_remark, result_remark)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $update = $pdo->prepare(
        'UPDATE results SET ca1 = ?, ca2 = ?, exam = ?, teacher_remark = ?, result_remark = ?, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND class_type = ? AND class_id = ?'
    );
    $saved = 0;
    $unchanged = 0;
    $activity = [];
    foreach ($normalised as $row) {
        $existingStmt->execute([$row['student_id'], $classType, $classId, $row['subject_id'], (int) $period['id']]);
        $existing = $existingStmt->fetch();
        $scores = $row['scores'];
        if ($existing) {
            $same = true;
            foreach (['ca1', 'ca2', 'exam'] as $field) {
                if ($existing[$field] !== null && (float) $existing[$field] !== $scores[$field]) {
                    $same = false;
                }
            }
            if (!$same) {
                throw new RuntimeException('A result already exists with different scores. Nothing in this batch was saved; review the existing result first.');
            }
            $update->execute([
                $scores['ca1'], $scores['ca2'], $scores['exam'],
                $row['teacher_remark'] !== '' ? $row['teacher_remark'] : null,
                $row['result_remark'] !== '' ? $row['result_remark'] : null,
                (int) $existing['id'], $classType, $classId
            ]);
            $activity[] = ['action' => 'result_updated', 'student_id' => $row['student_id'], 'result_id' => (int) $existing['id'], 'subject_id' => $row['subject_id']];
        } else {
            $insert->execute([
                $row['student_id'], $classType, $classId, $row['subject_id'], (int) $period['id'],
                $scores['ca1'], $scores['ca2'], $scores['exam'],
                $row['teacher_remark'] !== '' ? $row['teacher_remark'] : null,
                $row['result_remark'] !== '' ? $row['result_remark'] : null
            ]);
            $activity[] = ['action' => 'result_created', 'student_id' => $row['student_id'], 'result_id' => (int) $pdo->lastInsertId(), 'subject_id' => $row['subject_id']];
        }
        $saved++;
    }
    $pdo->commit();

    foreach ($activity as $item) {
        try {
            markazRecordActivity(
                $pdo,
                (int) ($_SESSION['admin_id'] ?? 0),
                currentAdminRole(),
                $item['action'],
                'student',
                $item['student_id'],
                [
                    'class_type' => $classType,
                    'class_id' => (int) $classId,
                    'subject_id' => $item['subject_id'],
                    'academic_period_id' => (int) $period['id'],
                    'result_id' => $item['result_id']
                ]
            );
        } catch (Throwable $auditError) {
            error_log('MARKAZ activity audit write failed: ' . $auditError->getMessage());
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'All validated results were saved successfully.',
        'saved' => $saved,
        'unchanged' => $unchanged,
        'academic_period_id' => (int) $period['id']
    ]);
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    error_log('MARKAZ results batch save failed: ' . $e->getMessage());
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $e->getMessage() === 'A result already exists with different scores. Nothing in this batch was saved; review the existing result first.' ? $e->getMessage() : 'The result batch could not be saved. Nothing in this batch was committed.']);
}
