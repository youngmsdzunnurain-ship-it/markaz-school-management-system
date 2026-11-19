<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
requireLogin();

function staffChatRespond(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$accountId = (int) ($_SESSION['admin_id'] ?? 0);
$role = currentAdminRole();
if ($accountId < 1 || !in_array($role, ['admin', 'exam_officer', 'teacher', 'management'], true)) {
    staffChatRespond(['success' => false, 'message' => 'Staff messaging is available to authorised staff accounts only.'], 403);
}
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method === 'POST') {
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $originHost = parse_url($origin, PHP_URL_HOST);
        $requestHost = preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (!is_string($originHost) || !hash_equals(strtolower($requestHost), strtolower($originHost))) {
            staffChatRespond(['success' => false, 'message' => 'Request origin could not be verified.'], 403);
        }
    }
    $input = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($input)) staffChatRespond(['success' => false, 'message' => 'Send a valid request.'], 400);
    $action = (string) ($input['action'] ?? '');
}

$membership = static function (PDO $pdo, int $conversationId, int $accountId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM staff_conversation_participants WHERE conversation_id = ? AND account_id = ? LIMIT 1');
    $stmt->execute([$conversationId, $accountId]);
    return (bool) $stmt->fetchColumn();
};

$avatarUrl = static function (?string $image): ?string {
    $image = trim((string) $image);
    if ($image === '' || str_contains($image, "\\0") || preg_match('~^(?:https?:|//|data:)~i', $image)) return null;
    $image = ltrim(str_replace('\\\\', '/', $image), '/');
    if (!str_starts_with($image, 'uploads/admins/')) return null;
    return '/' . $image;
};

if ($method === 'POST' && $action === 'heartbeat') {
    $pdo->prepare('INSERT INTO staff_presence (account_id, last_seen_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_seen_at = NOW()')->execute([$accountId]);
    $pdo->prepare(
        'UPDATE staff_conversation_participants p
         INNER JOIN (SELECT conversation_id, MAX(id) AS last_id FROM staff_messages GROUP BY conversation_id) latest
           ON latest.conversation_id = p.conversation_id
         SET p.last_delivered_message_id = GREATEST(p.last_delivered_message_id, latest.last_id)
         WHERE p.account_id = ?'
    )->execute([$accountId]);
    staffChatRespond(['success' => true]);
}

if ($method === 'GET' && $action === 'directory') {
    $stmt = $pdo->prepare(
        "SELECT a.id, a.name, a.role, a.profile_image,
                (pr.last_seen_at >= NOW() - INTERVAL 45 SECOND) AS is_online
         FROM admins a LEFT JOIN staff_presence pr ON pr.account_id = a.id
         WHERE a.is_active = 1 AND a.is_frozen = 0 AND a.id <> ?
           AND a.role IN ('admin','exam_officer','teacher','management') ORDER BY a.name"
    );
    $stmt->execute([$accountId]);
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($staff as &$person) {
        $person['avatar_url'] = $avatarUrl($person['profile_image']);
        $person['is_online'] = (bool) $person['is_online'];
        unset($person['profile_image']);
    }
    unset($person);
    staffChatRespond(['success' => true, 'staff' => $staff]);
}

if ($method === 'GET' && $action === 'conversations') {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.conversation_type, c.title, c.updated_at,
                (SELECT m.body FROM staff_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message,
                (SELECT m.created_at FROM staff_messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_message_at,
                (SELECT COUNT(*) FROM staff_messages um
                 WHERE um.conversation_id = c.id AND um.id > p.last_read_message_id AND um.sender_id <> ?) AS unread_count
         FROM staff_conversation_participants p
         INNER JOIN staff_conversations c ON c.id = p.conversation_id
         WHERE p.account_id = ?
         ORDER BY COALESCE(last_message_at, c.updated_at) DESC, c.id DESC"
    );
    $stmt->execute([$accountId, $accountId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($conversations as &$conversation) {
        $people = $pdo->prepare(
            'SELECT a.id, a.name, a.role, a.profile_image,
                    (pr.last_seen_at >= NOW() - INTERVAL 45 SECOND) AS is_online
             FROM staff_conversation_participants p
             INNER JOIN admins a ON a.id = p.account_id
             LEFT JOIN staff_presence pr ON pr.account_id = a.id
             WHERE p.conversation_id = ? ORDER BY a.name'
        );
        $people->execute([(int) $conversation['id']]);
        $conversation['participants'] = $people->fetchAll(PDO::FETCH_ASSOC);
        foreach ($conversation['participants'] as &$person) {
            $person['avatar_url'] = $avatarUrl($person['profile_image']);
            $person['is_online'] = (bool) $person['is_online'];
            unset($person['profile_image']);
        }
        unset($person);
        $conversation['unread_count'] = (int) $conversation['unread_count'];
    }
    unset($conversation);
    staffChatRespond(['success' => true, 'conversations' => $conversations]);
}

if ($method === 'GET' && $action === 'messages') {
    $conversationId = filter_var($_GET['conversation_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$conversationId || $conversationId < 1 || !$membership($pdo, (int) $conversationId, $accountId)) {
        staffChatRespond(['success' => false, 'message' => 'Conversation not found.'], 404);
    }
    $stmt = $pdo->prepare(
        'SELECT m.id, m.sender_id, a.name AS sender_name, a.role AS sender_role,
                a.profile_image AS sender_profile_image, m.body, m.created_at,
                (SELECT COUNT(*) FROM staff_conversation_participants rp
                 WHERE rp.conversation_id = m.conversation_id AND rp.account_id <> m.sender_id) AS recipient_count,
                (SELECT COUNT(*) FROM staff_conversation_participants dp
                 WHERE dp.conversation_id = m.conversation_id AND dp.account_id <> m.sender_id
                   AND dp.last_delivered_message_id >= m.id) AS delivered_count,
                (SELECT COUNT(*) FROM staff_conversation_participants rp
                 WHERE rp.conversation_id = m.conversation_id AND rp.account_id <> m.sender_id
                   AND rp.last_read_message_id >= m.id) AS read_count
         FROM staff_messages m INNER JOIN admins a ON a.id = m.sender_id
         WHERE m.conversation_id = ? ORDER BY m.id DESC LIMIT 100'
    );
    $stmt->execute([(int) $conversationId]);
    $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    $lastId = $messages ? (int) end($messages)['id'] : 0;
    if ($lastId > 0) {
        $read = $pdo->prepare('UPDATE staff_conversation_participants SET last_read_message_id = GREATEST(last_read_message_id, ?) WHERE conversation_id = ? AND account_id = ?');
        $read->execute([$lastId, (int) $conversationId, $accountId]);
    }
    foreach ($messages as &$message) {
        $message['sender_avatar_url'] = $avatarUrl($message['sender_profile_image']);
        $message['recipient_count'] = (int) $message['recipient_count'];
        $message['delivered_count'] = (int) $message['delivered_count'];
        $message['read_count'] = (int) $message['read_count'];
        unset($message['sender_profile_image']);
    }
    unset($message);
    staffChatRespond(['success' => true, 'messages' => $messages]);
}

if ($method === 'POST' && $action === 'create_conversation') {
    $recipientIds = $input['recipient_ids'] ?? [];
    if (!is_array($recipientIds) || count($recipientIds) < 1 || count($recipientIds) > 30) {
        staffChatRespond(['success' => false, 'message' => 'Choose between 1 and 30 staff members.'], 422);
    }
    $recipientIds = array_values(array_unique(array_filter(array_map('intval', $recipientIds), static fn($id) => $id > 0 && $id !== $accountId)));
    if (!$recipientIds) staffChatRespond(['success' => false, 'message' => 'Choose at least one other staff member.'], 422);
    $placeholders = implode(',', array_fill(0, count($recipientIds), '?'));
    $valid = $pdo->prepare("SELECT id FROM admins WHERE is_active = 1 AND is_frozen = 0 AND role IN ('admin','exam_officer','teacher','management') AND id IN ($placeholders)");
    $valid->execute($recipientIds);
    $validIds = array_map('intval', array_column($valid->fetchAll(PDO::FETCH_ASSOC), 'id'));
    sort($validIds);
    if (count($validIds) !== count($recipientIds)) staffChatRespond(['success' => false, 'message' => 'One or more selected staff accounts are unavailable.'], 422);
    $type = count($validIds) === 1 ? 'direct' : 'group';
    $title = trim((string) ($input['title'] ?? ''));
    if ($type === 'group' && ($title === '' || mb_strlen($title) > 120)) staffChatRespond(['success' => false, 'message' => 'Enter a group name of up to 120 characters.'], 422);

    if ($type === 'direct') {
        $find = $pdo->prepare(
            "SELECT c.id FROM staff_conversations c
             INNER JOIN staff_conversation_participants p1 ON p1.conversation_id = c.id AND p1.account_id = ?
             INNER JOIN staff_conversation_participants p2 ON p2.conversation_id = c.id AND p2.account_id = ?
             WHERE c.conversation_type = 'direct'
               AND (SELECT COUNT(*) FROM staff_conversation_participants px WHERE px.conversation_id = c.id) = 2
             LIMIT 1"
        );
        $find->execute([$accountId, $validIds[0]]);
        $existing = (int) $find->fetchColumn();
        if ($existing > 0) staffChatRespond(['success' => true, 'conversation_id' => $existing, 'existing' => true]);
    }

    $pdo->beginTransaction();
    try {
        $create = $pdo->prepare('INSERT INTO staff_conversations (conversation_type, title, created_by) VALUES (?, ?, ?)');
        $create->execute([$type, $type === 'group' ? $title : null, $accountId]);
        $conversationId = (int) $pdo->lastInsertId();
        $add = $pdo->prepare('INSERT INTO staff_conversation_participants (conversation_id, account_id) VALUES (?, ?)');
        $add->execute([$conversationId, $accountId]);
        foreach ($validIds as $id) $add->execute([$conversationId, $id]);
        $pdo->commit();
        staffChatRespond(['success' => true, 'conversation_id' => $conversationId]);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('MARKAZ staff conversation creation failed: ' . $error->getMessage());
        staffChatRespond(['success' => false, 'message' => 'The conversation could not be created.'], 500);
    }
}

if ($method === 'POST' && $action === 'send_message') {
    $conversationId = filter_var($input['conversation_id'] ?? null, FILTER_VALIDATE_INT);
    $body = trim((string) ($input['body'] ?? ''));
    if (!$conversationId || $conversationId < 1 || !$membership($pdo, (int) $conversationId, $accountId)) {
        staffChatRespond(['success' => false, 'message' => 'Conversation not found.'], 404);
    }
    if ($body === '' || mb_strlen($body) > 4000) staffChatRespond(['success' => false, 'message' => 'Messages must contain 1–4,000 characters.'], 422);
    $pdo->beginTransaction();
    try {
        $save = $pdo->prepare('INSERT INTO staff_messages (conversation_id, sender_id, body) VALUES (?, ?, ?)');
        $save->execute([(int) $conversationId, $accountId, $body]);
        $messageId = (int) $pdo->lastInsertId();
        $pdo->prepare('UPDATE staff_conversations SET updated_at = NOW() WHERE id = ?')->execute([(int) $conversationId]);
        $pdo->prepare('UPDATE staff_conversation_participants SET last_read_message_id = ? WHERE conversation_id = ? AND account_id = ?')->execute([$messageId, (int) $conversationId, $accountId]);
        $pdo->commit();
        staffChatRespond(['success' => true, 'message_id' => $messageId]);
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('MARKAZ staff message send failed: ' . $error->getMessage());
        staffChatRespond(['success' => false, 'message' => 'Your message could not be sent.'], 500);
    }
}

staffChatRespond(['success' => false, 'message' => 'Unsupported messaging request.'], 400);
