<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireLogin();

if (currentAdminRole() !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'School-wide activity is available to the Admin only.']);
    exit;
}

$limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
$date = (string) ($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date)) $date = date('Y-m-d');
$accountId = filter_var($_GET['account_id'] ?? 0, FILTER_VALIDATE_INT);
$accountId = $accountId && $accountId > 0 ? $accountId : 0;

$activityStmt = db()->prepare(
    'SELECT e.id, e.event_type, e.entity_type, e.entity_id, e.metadata, e.created_at,
            a.id AS account_id, COALESCE(a.name, e.account_name_snapshot) AS account_name, e.role_snapshot AS account_role
     FROM markaz_activity_events e
     LEFT JOIN admins a ON a.id = e.account_id
     WHERE DATE(e.created_at) = ? AND (? = 0 OR e.account_id = ?)
     ORDER BY e.created_at DESC, e.id DESC LIMIT ' . $limit
);
$activityStmt->execute([$date, $accountId, $accountId]);
$activity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

$aiStmt = db()->prepare(
    'SELECT e.id, e.event_type, e.metadata, e.created_at,
            a.id AS account_id, a.name AS account_name, a.role AS account_role
     FROM markaz_ai_audit_events e
     INNER JOIN admins a ON a.id = e.account_id
     WHERE DATE(e.created_at) = ? AND (? = 0 OR e.account_id = ?)
     ORDER BY e.created_at DESC, e.id DESC LIMIT ' . $limit
);
$aiStmt->execute([$date, $accountId, $accountId]);
$aiActivity = $aiStmt->fetchAll(PDO::FETCH_ASSOC);

$studentAiActivity = [];
if ($accountId === 0) {
    $studentAiStmt = db()->prepare(
        "SELECT e.id, e.event_type, e.metadata, e.created_at,
                s.id AS student_id,
                COALESCE(NULLIF(CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name), ''), e.student_name_snapshot) AS account_name,
                'student' AS account_role
         FROM markaz_ai_student_audit_events e
         LEFT JOIN students s ON s.id = e.student_id
         WHERE DATE(e.created_at) = ?
         ORDER BY e.created_at DESC, e.id DESC LIMIT " . $limit
    );
    $studentAiStmt->execute([$date]);
    $studentAiActivity = $studentAiStmt->fetchAll(PDO::FETCH_ASSOC);
}

$accounts = db()->query(
    "SELECT id, name, role FROM admins
     WHERE role IN ('admin','exam_officer','teacher','management') AND is_active = 1
     ORDER BY name"
)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'date' => $date,
    'account_id' => $accountId,
    'accounts' => $accounts,
    'activity' => $activity,
    'ai_activity' => $aiActivity,
    'student_ai_activity' => $studentAiActivity,
    'note' => 'This feed contains actions recorded by instrumented MARKAZ workflows from the date the audit migration was installed. It is not a retroactive audit of earlier actions or of uninstrumented workflows.'
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
