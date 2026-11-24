<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('Vary: Cookie');

requireAdminOrManagementPermission('students');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$ids = is_array($payload) ? ($payload['student_ids'] ?? []) : [];
if (!is_array($ids) || count($ids) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Provide no more than 500 student IDs.']);
    exit;
}

$ids = array_values(array_unique(array_filter(
    array_map(static fn($id): int => filter_var($id, FILTER_VALIDATE_INT) ?: 0, $ids),
    static fn(int $id): bool => $id > 0
)));

if (!$ids) {
    echo json_encode(['success' => true, 'photos' => new stdClass()]);
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = db()->prepare(
    "SELECT id, profile_image FROM students WHERE id IN ({$placeholders})"
);
$stmt->execute($ids);

$photos = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $path = trim((string) ($row['profile_image'] ?? ''));
    if ($path !== '' && !str_contains($path, '..') && !str_starts_with($path, '/')) {
        $photos[(string) (int) $row['id']] = $path;
    }
}

echo json_encode(['success' => true, 'photos' => $photos], JSON_UNESCAPED_SLASHES);
