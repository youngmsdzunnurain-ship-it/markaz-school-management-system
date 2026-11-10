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
if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid Arabic class ID.']);
    exit;
}
$db = db();
$find = $db->prepare('SELECT is_active FROM arabic_classes WHERE id = ? LIMIT 1');
$find->execute([$id]);
$class = $find->fetch();
if (!$class) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Arabic class not found.']);
    exit;
}
$newStatus = (int) $class['is_active'] === 1 ? 0 : 1;
$update = $db->prepare('UPDATE arabic_classes SET is_active = ? WHERE id = ?');
$update->execute([$newStatus, $id]);
echo json_encode([
    'success' => true,
    'message' => $newStatus === 1 ? 'Arabic class activated successfully.' : 'Arabic class deactivated successfully.',
    'is_active' => $newStatus
]);
