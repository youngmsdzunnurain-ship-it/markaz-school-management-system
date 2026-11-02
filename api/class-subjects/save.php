<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/ss-curriculum.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'POST request required.'
    ]);

    exit;
}

try {
    $input = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($input)) {
        throw new RuntimeException('Invalid request data.');
    }

    $classId = isset($input['class_id'])
        ? (int) $input['class_id']
        : 0;

    $subjects = $input['subjects'] ?? null;

    if ($classId < 1) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'A valid class ID is required.'
        ]);

        exit;
    }

    if (!is_array($subjects)) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Subjects must be provided as a list.'
        ]);

        exit;
    }

    $pdo = db();

    $classStmt = $pdo->prepare(
        'SELECT id, name
         FROM classes
         WHERE id = :id
         LIMIT 1'
    );

    $classStmt->execute([
        'id' => $classId
    ]);

    $class = $classStmt->fetch();

    if (!$class) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Class not found.'
        ]);

        exit;
    }

    ensureSeniorSecondaryCurriculum($pdo, $classId);

    $normalizedClass = strtoupper((string) preg_replace('/[\\s_-]+/', '', trim((string) $class['name'])));
    if (in_array($normalizedClass, ['SS1', 'SS2', 'SS3', 'SSS1', 'SSS2', 'SSS3'], true)) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'The approved 11-subject SS curriculum has been applied.',
            'class_id' => $classId,
            'subject_count' => 11
        ]);
        exit;
    }

    $cleanSubjects = [];
    $seen = [];

    foreach ($subjects as $index => $subject) {
        if (!is_array($subject)) {
            continue;
        }

        $subjectId = isset($subject['subject_id'])
            ? (int) $subject['subject_id']
            : 0;

        if ($subjectId < 1) {
            continue;
        }

        if (isset($seen[$subjectId])) {
            continue;
        }

        $seen[$subjectId] = true;

        $isRequired = !empty($subject['is_required']) ? 1 : 0;

        $cleanSubjects[] = [
            'subject_id' => $subjectId,
            'is_required' => $isRequired,
            'sort_order' => count($cleanSubjects) + 1
        ];
    }

    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare(
        'DELETE FROM class_subjects
         WHERE class_id = :class_id'
    );

    $deleteStmt->execute([
        'class_id' => $classId
    ]);

    if ($cleanSubjects) {
        $insertStmt = $pdo->prepare(
            'INSERT INTO class_subjects
                (class_id, subject_id, is_required, sort_order)
             VALUES
                (:class_id, :subject_id, :is_required, :sort_order)'
        );

        foreach ($cleanSubjects as $subject) {
            $insertStmt->execute([
                'class_id' => $classId,
                'subject_id' => $subject['subject_id'],
                'is_required' => $subject['is_required'],
                'sort_order' => $subject['sort_order']
            ]);
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Class subjects saved successfully.',
        'class_id' => $classId,
        'subject_count' => count($cleanSubjects)
    ]);

} catch (Throwable $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save class subjects.'
    ]);
}
