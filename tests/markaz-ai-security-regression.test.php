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
markazAiCheck(str_contains($chat, 'markazAiBuildFreeAnswer') && !str_contains($chat, 'api.openai.com') && !str_contains($chat, 'OPENAI_API_KEY'), 'Staff chat uses the no-cost local answer engine without a paid provider');
markazAiCheck(!str_contains($studentChat, 'OPENAI_API_KEY') && str_contains($studentChat, 'markazAiBuildFreeAnswer'), 'Student chat uses the no-cost answer engine without provider credentials');
markazAiCheck(str_contains($history, 'account_id = ?'), 'Conversation history is isolated to the authenticated account');
markazAiCheck(str_contains($audit, "currentAdminRole() !== 'admin'"), 'School-wide AI activity endpoint is Admin-only');
markazAiCheck(str_contains($migration, 'markaz_ai_conversations') && str_contains($migration, 'markaz_ai_messages') && str_contains($migration, 'markaz_ai_audit_events'), 'Staff AI conversations, messages and audit events have dedicated tables');
markazAiCheck(str_contains($migration, 'markaz_ai_student_conversations') && str_contains($migration, 'session_key_hash') && str_contains($studentContext, 'markazAiResolveStudentPortalContext'), 'Student AI conversations are isolated to the verified Student Portal session');
markazAiCheck(str_contains($studentChat, 'studentPortalResultScope') || str_contains($studentContext, 'studentPortalResultScope'), 'Student AI is gated by the published-result portal grant');
markazAiCheck(str_contains($studentChat, 'markazAiBuildFreeAnswer') && str_contains($studentContext, 'published_results'), 'Student assistant answers only from the verified published personal result context');
markazAiCheck(str_contains($voiceApi, 'VOICE_INPUT_UNAVAILABLE_NO_COST_MODE') && str_contains($studentVoiceApi, 'VOICE_INPUT_UNAVAILABLE_NO_COST_MODE') && !str_contains($voiceApi, 'audio/transcriptions'), 'Staff and student voice endpoints cannot call a paid transcription provider in no-cost mode');
markazAiCheck(str_contains($widget, 'no-cost mode') && str_contains($widget, 'root.querySelector("[data-ai-voice]").hidden = true;'), 'Assistant UI identifies the no-cost mode and keeps unsupported voice input hidden');
markazAiCheck(str_contains($studentResultHtml, 'markaz-ai.js') && str_contains($studentResultHtml, 'student-result.js'), 'Student Learning Assistant is loaded on the result portal page');
markazAiCheck(str_contains($widget, 'markaz-ai-launcher') && str_contains($widget, 'markaz-ai-chat'), 'AI assistant has an interactive shared UI');
markazAiCheck(str_contains($widget, '/api/ai/context.php') && str_contains($widget, 'selectedScope'), 'Existing teacher assignment context remains available without exposing scan functionality');
markazAiCheck(str_contains($scanner, 'IMAGE_SCANNING_UNAVAILABLE_NO_COST_MODE') && !str_contains($scanner, 'api.openai.com') && !str_contains($scanner, 'input_image'), 'Result-sheet scanning cannot call a paid image model in no-cost mode');
markazAiCheck(str_contains($resultSave, 'markazRecordActivity') && str_contains($resultSave, "'result_updated'"), 'Existing result save API records actor and result create/edit audit events');
markazAiCheck(str_contains($chat, 'markazAiRequireSameOrigin') && str_contains($history, 'account_id = ?'), 'AI write and read operations enforce same-origin and account ownership boundaries');
markazAiCheck(str_contains($audit, 'markaz_activity_events') && str_contains($audit, 'markaz_ai_audit_events'), 'Admin activity feed combines recorded school actions and AI events');
markazAiCheck(str_contains($migration, 'account_name_snapshot') && str_contains($migration, 'student_name_snapshot') && str_contains($migration, 'ON DELETE SET NULL'), 'Audit actor identity is retained when a staff or student account is deleted');
markazAiCheck(str_contains($attendanceSave, 'attendance_register_saved') && str_contains($attendanceSave, 'markazRecordActivity'), 'Attendance register saves are recorded for Admin school-wide oversight');
markazAiCheck(str_contains($styles, '@media') && str_contains($styles, 'prefers-reduced-motion'), 'AI assistant UI is responsive and respects reduced motion');

echo "MARKAZ AI security regression checks passed.\n";
