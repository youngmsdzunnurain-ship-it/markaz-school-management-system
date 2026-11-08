<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

$role = currentAdminRole();
if ($role === 'admin') {
    requireSeniorAdmin();
} elseif ($role === 'teacher') {
    requireResultStaff();
} else {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not permitted to edit memorization details.']);
    exit;
}
$studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
$portion = trim((string) ($_POST['memorization_portion'] ?? ''));
if (!$studentId || $studentId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
    exit;
}
if (mb_strlen($portion) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Portion of memorization is too long.']);
    exit;
}
$pdo = db();
$studentStmt = $pdo->prepare(
    'SELECT id, class_id, COALESCE(NULLIF(arabic_class_id, 0), arabia_class_id) AS arabic_class_id
     FROM students WHERE id = ? LIMIT 1'
);
$studentStmt->execute([$studentId]);
$student = $studentStmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Student not found.']);
    exit;
}
if ($role === 'teacher') {
    $classType = (string) ($_POST['class_type'] ?? '');
    $classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT);
    if (!in_array($classType, ['conventional', 'arabic'], true) || !$classId || $classId < 1) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'A valid assigned class is required.']);
        exit;
    }
    $studentClassId = $classType === 'arabic'
        ? (int) ($student['arabic_class_id'] ?? 0)
        : (int) ($student['class_id'] ?? 0);
    if ($studentClassId !== (int) $classId) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Student does not belong to the assigned class.']);
        exit;
    }
    $assignmentStmt = $pdo->prepare(
        'SELECT 1 FROM teacher_assignments
         WHERE account_id = ? AND class_type = ? AND class_id = ?
           AND subject_id IS NULL AND is_active = 1 LIMIT 1'
    );
    $assignmentStmt->execute([(int) ($_SESSION['admin_id'] ?? 0), $classType, (int) $classId]);
    if (!$assignmentStmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Full Class Access is required to edit memorization details.']);
        exit;
    }
}
$updateStmt = $pdo->prepare('UPDATE students SET memorization_portion_override = ? WHERE id = ?');
$updateStmt->execute([$portion !== '' ? $portion : null, $studentId]);
echo json_encode(['success' => true, 'message' => 'Memorization portion saved successfully.']);
