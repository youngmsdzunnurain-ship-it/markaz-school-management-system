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
    $subjectsByKey = [];
    $canonicalLabels = [
        'quran' => 'Quran',
        'english' => 'English',
        'mathematics' => 'Mathematics',
        'biology' => 'Biology',
        'chemistry' => 'Chemistry',
        'physics' => 'Physics',
        'civiceducation' => 'Civic Education',
        'agric' => 'Agric',
        'computer' => 'Computer',
        'foodandnutrition' => 'Food and Nutrition',
        'economics' => 'Economics',
    ];
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

    foreach ($rawSubjects as $subject) {
        $subjectKey = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', trim((string) $subject['name'])));
        $subjectKey = $aliases[$subjectKey] ?? $subjectKey;

        // Animal Husbandry is not offered in the approved subject library.
        if ($subjectKey === 'animalhusbandry') {
            continue;
        }

        if (!isset($subjectsByKey[$subjectKey])) {
            $subjectsByKey[$subjectKey] = $subject;
            continue;
        }

        // Prefer the exact canonical label over a legacy spelling/duplicate.
        if (isset($canonicalLabels[$subjectKey])
            && strcasecmp(trim((string) $subject['name']), $canonicalLabels[$subjectKey]) === 0) {
            $subjectsByKey[$subjectKey] = $subject;
        }
    }

    $subjects = array_values($subjectsByKey);
    usort($subjects, static fn(array $a, array $b): int => strnatcasecmp((string) $a['name'], (string) $b['name']));

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
