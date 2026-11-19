<?php
declare(strict_types=1);

function staffMessagingCheck(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
$api = file_get_contents(__DIR__ . '/../api/messages/index.php');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20261119_staff_messaging.sql');
$page = file_get_contents(__DIR__ . '/../public/staff-chat.html');
$js = file_get_contents(__DIR__ . '/../public/js/staff-chat.js');
$dashboard = file_get_contents(__DIR__ . '/../public/dashboard.html');
$dashboard = file_get_contents(__DIR__ . '/../public/dashboard.html');
$dashboardJs = file_get_contents(__DIR__ . '/../public/js/dashboard.js');
$chatCss = file_get_contents(__DIR__ . '/../public/css/staff-chat.css');

staffMessagingCheck(str_contains($api, "['admin', 'exam_officer', 'teacher', 'management']"), 'only authenticated staff roles can use messaging');
staffMessagingCheck(str_contains($api, 'staff_conversation_participants') && str_contains($api, '$membership($pdo'), 'every conversation read and send is restricted to its participants');
staffMessagingCheck(str_contains($api, 'is_active = 1 AND is_frozen = 0'), 'inactive and frozen staff cannot be added to conversations');
staffMessagingCheck(str_contains($api, 'mb_strlen($body) > 4000'), 'message length is bounded');
staffMessagingCheck(str_contains($migration, 'FOREIGN KEY (conversation_id)'), 'conversation records have database-enforced ownership relationships');
staffMessagingCheck(str_contains($migration, 'FOREIGN KEY (account_id)'), 'participant records are linked to valid staff accounts');
staffMessagingCheck(str_contains($page, 'staff-chat.js') && str_contains($js, 'create_conversation'), 'staff chat page supports conversation creation');
staffMessagingCheck(str_contains($js, 'startDirectConversation') && str_contains($js, 'Start chat'), 'staff directory offers immediate direct-chat actions');
staffMessagingCheck(str_contains($js, 'formatRole') && str_contains($js, 'messageLoadSequence'), 'staff roles are labelled and fast conversation switching ignores stale message responses');
staffMessagingCheck(str_contains($page, 'id="backToInbox"') && str_contains($js, 'backToInbox'), 'mobile thread view has an explicit back-to-staff-list control');
staffMessagingCheck(str_contains($js, 'textContent'), 'message content is rendered as text rather than executable HTML');
staffMessagingCheck(str_contains($dashboard, 'href="./staff-chat.html" class="nav-link"') && !str_contains($dashboard, 'href="./staff-chat.html" class="nav-link" data-role-access'), 'staff chat navigation is visible independently of delayed dashboard role initialization');
staffMessagingCheck(str_contains($dashboardJs, 'requiredRole === "staff_messaging"') && str_contains($dashboardJs, '["admin", "exam_officer", "teacher", "management"]'), 'all supported staff roles are explicitly granted chat navigation');
staffMessagingCheck(str_contains($dashboard, 'data-role-access="staff_messaging"') && str_contains($dashboardJs, '"Let\'s Chat": "staff-chat.html"'), 'Let's Chat is available from dashboard quick shortcuts for authorised staff');
staffMessagingCheck(str_contains($dashboardJs, 'item[0] === "staff_messaging"'), 'management dashboard also includes the staff chat shortcut');
staffMessagingCheck(str_contains($page, 'markaz-chat-watermark') && str_contains($chatCss, '.markaz-chat-watermark'), 'staff chat renders a branded school watermark');
staffMessagingCheck(str_contains($page, '20261119-2'), 'staff chat assets use a refreshed cache version');
staffMessagingCheck(str_contains($dashboard, 'href="./staff-chat.html" class="nav-link"') && !str_contains($dashboard, 'href="./staff-chat.html" class="nav-link" data-role-access'), 'staff chat navigation is not hidden behind delayed dashboard role initialization');
echo "MARKAZ staff messaging regression checks passed.\n";
