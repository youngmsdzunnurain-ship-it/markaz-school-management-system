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

staffMessagingCheck(str_contains($api, "['admin', 'exam_officer', 'teacher', 'management']"), 'only authenticated staff roles can use messaging');
staffMessagingCheck(str_contains($api, "Cache-Control: no-store, no-cache, must-revalidate, private"), 'staff directory and participant avatar responses cannot be served from stale caches');
staffMessagingCheck(str_contains($api, 'staff_conversation_participants') && str_contains($api, '$membership($pdo'), 'every conversation read and send is restricted to its participants');
staffMessagingCheck(str_contains($api, 'is_active = 1 AND is_frozen = 0'), 'inactive and frozen staff cannot be added to conversations');
staffMessagingCheck(str_contains($api, 'mb_strlen($body) > 4000'), 'message length is bounded');
staffMessagingCheck(str_contains($migration, 'FOREIGN KEY (conversation_id)'), 'conversation records have database-enforced ownership relationships');
staffMessagingCheck(str_contains($migration, 'FOREIGN KEY (account_id)'), 'participant records are linked to valid staff accounts');
staffMessagingCheck(str_contains($page, 'staff-chat.js') && str_contains($js, 'create_conversation'), 'staff chat page supports conversation creation');
staffMessagingCheck(str_contains($js, 'startDirectConversation') && str_contains($js, 'Start chat'), 'staff directory offers immediate direct-chat actions');
staffMessagingCheck(str_contains($js, 'openConversation(Number(data.conversation_id),person)') && str_contains($js, 'nextAvatar.id="threadAvatar"') && str_contains($js, 'previousAvatar.replaceWith(nextAvatar)'), 'direct chat opens from the selected staff identity and replaces the thread avatar without losing its target');
staffMessagingCheck(str_contains($js, 'formatRole') && str_contains($js, 'messageLoadSequence'), 'staff roles are labelled and fast conversation switching ignores stale message responses');
staffMessagingCheck(str_contains($page, 'id="backToInbox"') && str_contains($js, 'backToInbox'), 'mobile thread view has an explicit back-to-staff-list control');
$dashboard = file_get_contents(__DIR__ . '/../public/dashboard.html');
$dashboardJs = file_get_contents(__DIR__ . '/../public/js/dashboard.js');
$onlinePage = file_get_contents(__DIR__ . '/../public/online-class.html');
$onlineCss = file_get_contents(__DIR__ . '/../public/css/online-class.css');
staffMessagingCheck(str_contains($dashboard, 'href="./staff-chat.html" class="nav-link" data-role-access="staff_messaging"'), 'Lets Chat is permanently present in the main sidebar with staff-only access control');
staffMessagingCheck(str_contains($dashboardJs, 'requiredRole === "staff_messaging"') && str_contains($dashboardJs, '["admin", "exam_officer", "teacher", "management"].includes(role)'), 'staff messaging navigation is limited to the four authorised staff roles');
staffMessagingCheck(str_contains($onlineCss, '.online-workspace-page #tab-overview{padding:24px 0}'), 'Online Class overview has balanced space above and below its summary cards');
staffMessagingCheck(str_contains($onlineCss, '.online-workspace-page .online-stat::before') && str_contains($onlineCss, 'background:var(--gold,#c8a43a)'), 'Online Class summary cards use the shared dashboard gold top accent');
staffMessagingCheck(str_contains($onlinePage, 'online-class.css?v=20261120-overview-gold1'), 'Online Class stylesheet cache version is refreshed after overview styling');
staffMessagingCheck(str_contains($dashboard, 'dashboard.js?v=20261120-staff-messaging-role4'), 'dashboard role-access changes use a fresh JavaScript cache version');
staffMessagingCheck(str_contains($js, 'textContent'), 'message content is rendered as text rather than executable HTML');
echo "MARKAZ staff messaging regression checks passed.\n";
