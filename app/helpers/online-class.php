<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../middleware/auth.php';

function onlineClassStaffAccess(): void
{
    $role = currentAdminRole();
    if ($role === 'admin') return;
    if (!in_array($role, ['exam_officer', 'teacher', 'management'], true)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>'Staff access is required.']);
        exit;
    }
    $stmt = db()->prepare('SELECT is_allowed FROM account_permissions WHERE account_id = ? AND permission_key = ? LIMIT 1');
    $stmt->execute([(int)($_SESSION['admin_id'] ?? 0), 'online_class_access']);
    if ((int)$stmt->fetchColumn() !== 1) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>'Online Class access has not been assigned to this account.']);
        exit;
    }
}
function onlineClassInput(): array
{
    $data = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($data)) onlineClassJsonError(400, 'Send a valid JSON request.');
    return $data;
}
function onlineClassJsonError(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>false,'message'=>$message], JSON_UNESCAPED_UNICODE);
    exit;
}
function onlineClassJson(array $data, int $status=200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function onlineClassStudentSession(): int
{
    startSecureSession();
    $id = (int)($_SESSION['online_student_id'] ?? 0);
    if ($id < 1) onlineClassJsonError(401, 'Please sign in to your Online Class account.');
    $stmt = db()->prepare('SELECT id, is_active FROM online_class_students WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student || (int)$student['is_active'] !== 1) { unset($_SESSION['online_student_id']); onlineClassJsonError(401, 'This Online Class account is unavailable.'); }
    return $id;
}
function onlineClassGrade(float $score): array
{
    if ($score >= 80) return ['grade' => 'A', 'remark' => 'Awesome'];
    if ($score >= 70) return ['grade' => 'B', 'remark' => 'Phenomenal'];
    if ($score >= 50) return ['grade' => 'C', 'remark' => 'Impressive'];
    if ($score >= 30) return ['grade' => 'D', 'remark' => 'Promising'];
    return ['grade' => 'E', 'remark' => 'Poor'];
}

function onlineClassValidateScores(array $input): array
{
    $limits = ['attendance_score'=>20, 'test_score'=>20, 'exam_score'=>60];
    $scores = [];
    foreach ($limits as $key=>$max) {
        $raw = $input[$key] ?? null;
        if ($raw === '' || $raw === null) { $scores[$key] = null; continue; }
        if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > $max) {
            onlineClassJsonError(422, ucfirst(str_replace('_',' ', $key)) . " must be between 0 and {$max}.");
        }
        $scores[$key] = round((float)$raw, 2);
    }
    return $scores;
}
