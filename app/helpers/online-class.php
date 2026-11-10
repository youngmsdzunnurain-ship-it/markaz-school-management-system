<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../middleware/auth.php';

function onlineClassStaffAccess(): void
{
    requireOnlineClassAccess();
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
    $portalEnabled = db()->query("SELECT setting_value FROM settings WHERE setting_key = 'online_class_student_access' LIMIT 1")->fetchColumn();
    if (!in_array(strtolower(trim((string)$portalEnabled)), ['1', 'true', 'yes', 'on'], true)) {
        unset($_SESSION['online_student_id'], $_SESSION['online_student_name']);
        onlineClassJsonError(403, 'Online Class student access is currently closed.');
    }
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

function onlineClassTutorRemark(float $score): string
{
    if ($score >= 80) return 'Mashallah! Your dedication and excellent effort are truly commendable. May Allah increase you in beneficial knowledge.';
    if ($score >= 70) return 'Well done. Your steady effort is showing beautiful progress. Keep revising and moving forward.';
    if ($score >= 50) return 'Your progress is promising. Continue with regular revision and confidence; every step matters.';
    if ($score >= 30) return 'You have made a beginning. With patient practice and consistent revision, you can make stronger progress.';
    return 'Please do not be discouraged. Learning is a journey; take it one step at a time and keep trying.';
}

function onlineClassDirectorRemark(float $score): string
{
    if ($score >= 80) return 'Excellent achievement. May Allah preserve your knowledge and grant you continued success.';
    if ($score >= 70) return 'A very good effort. We encourage you to maintain this consistency and continue striving.';
    if ($score >= 50) return 'Your progress is promising. More regular revision will help you reach the next level.';
    if ($score >= 30) return 'We appreciate your effort. With support, patience and consistency, further improvement is within reach.';
    return 'Every learner deserves encouragement. Let us continue supporting your learning journey with patience and care.';
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

