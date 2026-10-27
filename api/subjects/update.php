<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

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

    $allowedLevels = [
        'Nursery',
        'Primary',
        'Junior Secondary',
        'Senior Secondary'
    ];

    if ($code === '') {
        $words = preg_split('/[^A-Za-z0-9]+/', strtoupper($name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$words) {
            throw new RuntimeException('A valid subject name is required to generate its code.');
        }

        if (count($words) > 1) {
            $base = '';
            foreach ($words as $word) {
                $base .= $word[0];
            }
        } else {
            $base = substr($words[0], 0, 4);
        }

        $base = substr($base, 0, 12);
        $candidate = $base;
        $suffix = 2;

        while (true) {
            $codeSql = 'SELECT id FROM subjects WHERE code = ?'
                . ' AND id <> ?'
                . ' LIMIT 1';
            $codeCheck = db()->prepare($codeSql);
            $codeParams = [$candidate];
            $codeParams[] = $id;
            $codeCheck->execute($codeParams);
            if (!$codeCheck->fetch()) {
                break;
            }
            $candidate = $base . $suffix;
            $suffix++;
            if ($suffix > 99999) {
                throw new RuntimeException('Could not generate a unique subject code.');
            }
        }
        $code = $candidate;
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
        'SELECT id, category
         FROM subjects
         WHERE id = ?
         LIMIT 1'
    );

    $check->execute([$id]);

    $existingSubject = $check->fetch(PDO::FETCH_ASSOC);
    if (!$existingSubject) {
        throw new RuntimeException('Subject not found.');
    }

    if ($category === '') {
        $category = (string) ($existingSubject['category'] ?? '');
    }

    if (!in_array($category, $allowedLevels, true)
        && $category !== (string) ($existingSubject['category'] ?? '')) {
        throw new RuntimeException('Choose a valid school level.');
    }

    $duplicate = db()->prepare(
        'SELECT id
         FROM subjects
         WHERE name = ?
           AND id <> ?
         LIMIT 1'
    );

    $duplicate->execute([$name, $id]);

    if ($duplicate->fetch()) {
        throw new RuntimeException(
            'Another subject with this name already exists.'
        );
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
