<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

try {
    $stmt = db()->query(
        'SELECT
            id,
            name,
            code,
            category,
            max_ca1,
            max_ca2,
            max_exam,
            is_active,
            created_at,
            updated_at
        FROM subjects
        ORDER BY
            is_active DESC,
            name ASC,
            id ASC'
    );

    $rawSubjects = $stmt->fetchAll();
    $subjects = [];
    $seenSubjectKeys = [];

    foreach ($rawSubjects as $subject) {
        $subjectKey = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', trim((string) $subject['name'])));
        $aliases = [
            'math' => 'mathematics',
            'maths' => 'mathematics',
            'englishlanguage' => 'english',
            'quranicstudies' => 'quran',
            'agriculture' => 'agric',
            'agriculturalscience' => 'agric',
            'computerstudies' => 'computer',
            'ict' => 'computer',
            'foodnutrition' => 'foodandnutrition',
        ];
        $subjectKey = $aliases[$subjectKey] ?? $subjectKey;

        // Hide Animal Husbandry from this subject-entry library while retaining
        // its database row and any historical score records.
        if ($subjectKey === 'animalhusbandry' || isset($seenSubjectKeys[$subjectKey])) {
            continue;
        }

        $seenSubjectKeys[$subjectKey] = true;
        $subjects[] = $subject;
    }

    foreach ($subjects as &$subject) {
        $subject['id'] = (int) $subject['id'];
        $subject['max_ca1'] = (float) $subject['max_ca1'];
        $subject['max_ca2'] = (float) $subject['max_ca2'];
        $subject['max_exam'] = (float) $subject['max_exam'];
        $subject['is_active'] = (bool) $subject['is_active'];
    }

    unset($subject);

    echo json_encode([
        'success' => true,
        'subjects' => $subjects
    ]);

} catch (Throwable $error) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load subjects.'
    ]);
}
