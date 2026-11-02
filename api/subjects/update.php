<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/ss-curriculum.php';
require_once __DIR__ . '/../../app/helpers/subject-categories.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
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

    $id = isset($input['id']) ? (int) $input['id'] : 0;
    $name = trim((string) ($input['name'] ?? ''));
    $code = strtoupper(trim((string) ($input['code'] ?? '')));
    $category = trim((string) ($input['category'] ?? ''));
    $subjectGroup = trim((string) ($input['subject_group'] ?? ''));

    $allowedLevels = [
        'Nursery',
        'Primary',
        'Junior Secondary',
        'Senior Secondary'
    ];

    if ($code === '') {
        $existingCodes = db()->query("SELECT code FROM subjects WHERE code IS NOT NULL AND TRIM(code) <> ''")->fetchAll(PDO::FETCH_COLUMN);
        $code = generateUniqueSubjectCode($name, $existingCodes);
    }

    $maxCa1 = isset($input['max_ca1'])
        ? (float) $input['max_ca1']
        : 20.00;

    $maxCa2 = isset($input['max_ca2'])
        ? (float) $input['max_ca2']
        : 20.00;

    $maxExam = isset($input['max_exam'])
        ? (float) $input['max_exam']
        : 60.00;

    if ($id <= 0) {
        throw new RuntimeException('Invalid subject.');
    }

    if ($name === '') {
        throw new RuntimeException('Subject name is required.');
    }

    if ($maxCa1 < 0 || $maxCa2 < 0 || $maxExam < 0) {
        throw new RuntimeException('Assessment maximums cannot be negative.');
    }

    $total = $maxCa1 + $maxCa2 + $maxExam;

    if (abs($total - 100) > 0.001) {
        throw new RuntimeException(
            'CA1, CA2 and Exam maximums must add up to 100.'
        );
    }

    $check = db()->prepare(
        'SELECT id, category, subject_group
         FROM subjects
         WHERE id = ?
         LIMIT 1'
    );

    $check->execute([$id]);

    $existingSubject = $check->fetch(PDO::FETCH_ASSOC);
    if (!$existingSubject) {
        throw new RuntimeException('Subject not found.');
    }

    if ($subjectGroup === '') $subjectGroup = (string) ($existingSubject['subject_group'] ?? suggestSubjectGroup($name));
    $allowedGroups = ['Core', 'Science', 'Islamic Studies', 'Languages', 'Humanities', 'Vocational', 'Other'];
    if (!in_array($subjectGroup, $allowedGroups, true)) throw new RuntimeException('Choose a valid subject group.');

    if ($category === '') {
        $category = (string) ($existingSubject['category'] ?? '');
    }

    if (!in_array($category, $allowedLevels, true)
        && $category !== (string) ($existingSubject['category'] ?? '')) {
        throw new RuntimeException('Choose a valid school level.');
    }

    $duplicateRows = db()->query('SELECT id, name FROM subjects WHERE id <> ' . (int) $id)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($duplicateRows as $duplicateRow) {
        if (normalizeSeniorSubjectKey((string) $duplicateRow['name']) === normalizeSeniorSubjectKey($name)) {
            throw new RuntimeException('Another subject already uses this name or a matching name variant.');
        }
    }

    $duplicateCode = db()->prepare(
        'SELECT id FROM subjects WHERE code = ? AND id <> ? LIMIT 1'
    );
    $duplicateCode->execute([$code, $id]);
    if ($duplicateCode->fetch()) {
        throw new RuntimeException('Another subject already uses this code.');
    }

    $stmt = db()->prepare(
        'UPDATE subjects
         SET
            name = ?,
            code = ?,
            category = ?,
            subject_group = ?,
            max_ca1 = ?,
            max_ca2 = ?,
            max_exam = ?,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = ?'
    );

    $stmt->execute([
        $name,
        $code !== '' ? $code : null,
        $category !== '' ? $category : null,
        $subjectGroup,
        $maxCa1,
        $maxCa2,
        $maxExam,
        $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Subject updated successfully.'
    ]);

} catch (Throwable $error) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $error->getMessage()
    ]);
}
