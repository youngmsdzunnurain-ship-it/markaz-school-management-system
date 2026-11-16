<?php
declare(strict_types=1);

function markazAiCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$helper = file_get_contents(__DIR__ . '/../app/helpers/markaz-ai.php');
$context = file_get_contents(__DIR__ . '/../api/ai/context.php');
$chat = file_get_contents(__DIR__ . '/../api/ai/chat.php');
$scanner = file_get_contents(__DIR__ . '/../api/ai/scan-result.php');
$resultSave = file_get_contents(__DIR__ . '/../api/results/save.php');
$history = file_get_contents(__DIR__ . '/../api/ai/conversations.php');
$audit = file_get_contents(__DIR__ . '/../api/ai/admin-activity.php');
$migration = file_get_contents(__DIR__ . '/../database/migrations/20261116_markaz_ai_foundation.sql');
$widget = file_get_contents(__DIR__ . '/../public/js/markaz-ai.js');
$styles = file_get_contents(__DIR__ . '/../public/css/markaz-ai.css');
$studentChat = file_get_contents(__DIR__ . '/../api/ai/student-chat.php');
$studentContext = file_get_contents(__DIR__ . '/../app/helpers/markaz-ai.php');
$studentResultHtml = file_get_contents(__DIR__ . '/../public/student-result.html');
$attendanceSave = file_get_contents(__DIR__ . '/../api/attendance/register.php');
$voiceApi = file_get_contents(__DIR__ . '/../api/ai/transcribe.php');
$studentVoiceApi = file_get_contents(__DIR__ . '/../api/ai/student-transcribe.php');

markazAiCheck(str_contains($chat, 'requireLogin()'), 'AI chat requires an authenticated MARKAZ session');
markazAiCheck(str_contains($helper, 'markazAiResolveScope') && str_contains($helper, 'teacher_assignments'), 'AI context is resolved from server-side role and teacher assignments');
markazAiCheck(str_contains($helper, 'managementPermissionAllowed'), 'Management AI context checks each assigned workspace permission');
markazAiCheck(str_contains($helper, 'markazAiLoadWorkspaceContext') && str_contains($chat, 'markazAiLoadWorkspaceContext'), 'AI answers are grounded in live role-scoped workspace state');
markazAiCheck(str_contains($context, 'scan_assignments') && str_contains($helper, 'teacher_assignments'), 'Teacher scanner choices are generated from persisted assignments');
markazAiCheck(str_contains($helper, "role === 'admin'") && str_contains($helper, 'school_wide_audit'), 'Only Admin receives school-wide audit capability');
markazAiCheck(str_contains($chat, 'store') && str_contains($chat, 'false'), 'Provider requests disable provider-side response storage');
markazAiCheck(str_contains($chat, 'OPENAI_API_KEY') && !str_contains($widget, 'OPENAI_API_KEY'), 'Provider credentials remain server-side');
markazAiCheck(str_contains($history, 'account_id = ?'), 'Conversation history is isolated to the authenticated account');
markazAiCheck(str_contains($audit, "currentAdminRole() !== 'admin'"), 'School-wide AI activity endpoint is Admin-only');
markazAiCheck(str_contains($migration, 'markaz_ai_conversations') && str_contains($migration, 'markaz_ai_messages') && str_contains($migration, 'markaz_ai_audit_events'), 'Staff AI conversations, messages and audit events have dedicated tables');
markazAiCheck(str_contains($migration, 'markaz_ai_student_conversations') && str_contains($migration, 'session_key_hash') && str_contains($studentContext, 'markazAiResolveStudentPortalContext'), 'Student AI conversations are isolated to the verified Student Portal session');
markazAiCheck(str_contains($studentChat, 'studentPortalResultScope') || str_contains($studentContext, 'studentPortalResultScope'), 'Student AI is gated by the published-result portal grant');
markazAiCheck(str_contains($studentChat, 'markazAiStudentBuildInstructions') && str_contains($studentChat, 'store'), 'Student AI receives only the published personal result context and disables provider storage');
markazAiCheck(str_contains($voiceApi, 'markazAiRequireSameOrigin') && str_contains($voiceApi, '5 * 1024 * 1024') && str_contains($studentVoiceApi, 'markazAiResolveStudentPortalContext'), 'Voice transcription enforces same-origin, upload limits and student-session scope');
markazAiCheck(str_contains($widget, 'MediaRecorder') && str_contains($widget, 'endpoint("transcribe")'), 'Assistant offers microphone input with review-before-send transcription');
markazAiCheck(str_contains($studentResultHtml, 'markaz-ai.js') && str_contains($studentResultHtml, 'student-result.js'), 'Student Learning Assistant is loaded on the result portal page');
markazAiCheck(str_contains($widget, 'markaz-ai-launcher') && str_contains($widget, 'markaz-ai-chat'), 'AI assistant has an interactive shared UI');
markazAiCheck(str_contains($widget, '/api/ai/context.php') && str_contains($widget, 'selectedScope'), 'Teacher result scans submit a server-verified assigned class and subject');
markazAiCheck(str_contains($scanner, 'requireTeacherAssignment') && str_contains($scanner, "'saved_to_results' => false") && !str_contains($scanner, 'INSERT INTO results'), 'Result-sheet scanning is assignment-scoped and review-only');
markazAiCheck(str_contains($resultSave, 'markazRecordActivity') && str_contains($resultSave, "'result_updated'"), 'Existing result save API records actor and result create/edit audit events');
markazAiCheck(str_contains($chat, 'markazAiRequireSameOrigin') && str_contains($history, 'account_id = ?'), 'AI write and read operations enforce same-origin and account ownership boundaries');
markazAiCheck(str_contains($audit, 'markaz_activity_events') && str_contains($audit, 'markaz_ai_audit_events'), 'Admin activity feed combines recorded school actions and AI events');
markazAiCheck(str_contains($migration, 'account_name_snapshot') && str_contains($migration, 'student_name_snapshot') && str_contains($migration, 'ON DELETE SET NULL'), 'Audit actor identity is retained when a staff or student account is deleted');
markazAiCheck(str_contains($attendanceSave, 'attendance_register_saved') && str_contains($attendanceSave, 'markazRecordActivity'), 'Attendance register saves are recorded for Admin school-wide oversight');
markazAiCheck(str_contains($styles, '@media') && str_contains($styles, 'prefers-reduced-motion'), 'AI assistant UI is responsive and respects reduced motion');

echo "MARKAZ AI security regression checks passed.\n";
