<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
require_once __DIR__ . '/../../app/helpers/markaz-ai.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireLogin();
$accountId = (int) $_SESSION['admin_id'];
$role = currentAdminRole();
try {
    $scope = markazAiResolveScope(db(), $accountId, $role);
} catch (Throwable $error) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
    exit;
}
$scanAssignments = [];
if ($role === 'teacher') {
    foreach ($scope['assignments'] as $assignment) {
        $type = (string) $assignment['class_type'];
        $classId = (int) $assignment['class_id'];
        $subjectId = $assignment['subject_id'] === null ? 0 : (int) $assignment['subject_id'];
        $classLabel = ucfirst($type) . ' class #' . $classId;
        $classTable = $type === 'arabic' ? 'arabic_classes' : 'classes';
        $classStmt = db()->prepare("SELECT name FROM {$classTable} WHERE id = ? LIMIT 1");
        $classStmt->execute([$classId]);
        $foundName = $classStmt->fetchColumn();
        if ($foundName) $classLabel = (string) $foundName . ' (' . ucfirst($type) . ')';

        if ($subjectId > 0) {
            $subjectStmt = db()->prepare('SELECT name FROM subjects WHERE id = ? AND is_active = 1 LIMIT 1');
            $subjectStmt->execute([$subjectId]);
            $subjectName = $subjectStmt->fetchColumn();
            if ($subjectName) $scanAssignments[] = [
                'class_id' => $classId, 'class_type' => $type,
                'subject_id' => $subjectId, 'label' => $classLabel . ' — ' . $subjectName
            ];
            continue;
        }

        $mapping = $type === 'arabic' ? 'arabic_class_subjects' : 'class_subjects';
        $classColumn = $type === 'arabic' ? 'arabic_class_id' : 'class_id';
        $subjectStmt = db()->prepare(
            "SELECT s.id, s.name FROM {$mapping} cs
             INNER JOIN subjects s ON s.id = cs.subject_id
             WHERE cs.{$classColumn} = ? AND s.is_active = 1
             ORDER BY s.name"
        );
        $subjectStmt->execute([$classId]);
        foreach ($subjectStmt->fetchAll(PDO::FETCH_ASSOC) as $subject) {
            $scanAssignments[] = [
                'class_id' => $classId, 'class_type' => $type,
                'subject_id' => (int) $subject['id'],
                'label' => $classLabel . ' — ' . $subject['name']
            ];
        }
    }
}
echo json_encode([
    'success' => true,
    'role' => $role,
    'capabilities' => $scope['capabilities'],
    'scan_assignments' => $scanAssignments
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
