<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
header('Content-Type: application/json; charset=utf-8');
requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
$id = (int) ($_POST['id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$sortOrder = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
$isActive = filter_var($_POST['is_active'] ?? 1, FILTER_VALIDATE_INT);
if ($id <= 0 || $name === '' || $sortOrder === false || !in_array($isActive, [0, 1], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Provide a valid Arabic class ID, name, display order, and status.']);
    exit;
}
$db = db();
$exists = $db->prepare('SELECT id FROM arabic_classes WHERE id = ? LIMIT 1');
$exists->execute([$id]);
if (!$exists->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Arabic class not found.']);
    exit;
}
$duplicate = $db->prepare('SELECT id FROM arabic_classes WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND id <> ? LIMIT 1');
$duplicate->execute([$name, $id]);
if ($duplicate->fetchColumn()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Another Arabic class already uses this name.']);
    exit;
}
$update = $db->prepare('UPDATE arabic_classes SET name = ?, sort_order = ?, is_active = ? WHERE id = ?');
$update->execute([$name, $sortOrder, $isActive, $id]);
echo json_encode(['success' => true, 'message' => 'Arabic class updated successfully.']);
