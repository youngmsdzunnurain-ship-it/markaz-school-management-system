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
                . ' LIMIT 1';
            $codeCheck = db()->prepare($codeSql);
            $codeParams = [$candidate];
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

    if (!in_array($category, $allowedLevels, true)) {
        throw new RuntimeException('Choose a valid school level.');
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

    $duplicate = db()->prepare(
        'SELECT id
         FROM subjects
         WHERE name = ?
         LIMIT 1'
    );

    $duplicate->execute([$name]);

    if ($duplicate->fetch()) {
        throw new RuntimeException(
            'A subject with this name already exists.'
        );
    }

    if ($code !== '') {
        $codeCheck = db()->prepare(
            'SELECT id
             FROM subjects
             WHERE code = ?
             LIMIT 1'
        );

        $codeCheck->execute([$code]);

        if ($codeCheck->fetch()) {
            throw new RuntimeException(
                'A subject with this code already exists.'
            );
        }
    }

    $stmt = db()->prepare(
        'INSERT INTO subjects
            (
                name,
                code,
                category,
                max_ca1,
                max_ca2,
                max_exam,
                is_active
            )
         VALUES (?, ?, ?, ?, ?, ?, 1)'
    );

    $stmt->execute([
        $name,
        $code !== '' ? $code : null,
        $category !== '' ? $category : null,
        $maxCa1,
        $maxCa2,
        $maxExam
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Subject created successfully.',
        'subject_id' => (int) db()->lastInsertId()
    ]);

} catch (Throwable $error) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => $error->getMessage()
    ]);
}
