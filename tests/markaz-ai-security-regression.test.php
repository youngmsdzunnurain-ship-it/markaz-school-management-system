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
markazAiCheck(str_contains($studentChat, 'STUDENT_AI_DISABLED') && str_contains($studentChat, 'http_response_code(403)'), 'Student chat endpoint rejects all student requests server-side');
markazAiCheck(str_contains($history, 'account_id = ?'), 'Conversation history is isolated to the authenticated account');
markazAiCheck(str_contains($audit, "currentAdminRole() !== 'admin'"), 'School-wide AI activity endpoint is Admin-only');
markazAiCheck(str_contains($migration, 'markaz_ai_conversations') && str_contains($migration, 'markaz_ai_messages') && str_contains($migration, 'markaz_ai_audit_events'), 'Staff AI conversations, messages and audit events have dedicated tables');
markazAiCheck(str_contains($migration, 'markaz_ai_student_conversations') && str_contains($migration, 'session_key_hash'), 'Legacy student AI tables remain structurally intact without being exposed to students');
markazAiCheck(str_contains($studentChat, 'STUDENT_AI_DISABLED') && str_contains($studentChat, 'http_response_code(403)'), 'Student chat endpoint rejects all student requests server-side');
markazAiCheck(str_contains($studentContext, 'markazAiResolveStudentPortalContext'), 'Student result context resolver remains available to the ordinary student result workflow');
markazAiCheck(str_contains($voiceApi, 'VOICE_INPUT_UNAVAILABLE_NO_COST_MODE') && str_contains($studentVoiceApi, 'STUDENT_AI_DISABLED') && !str_contains($voiceApi, 'audio/transcriptions'), 'Staff voice and student transcription endpoints cannot call a paid provider in no-cost mode');
markazAiCheck(str_contains($widget, 'no-cost mode') && str_contains($widget, 'root.querySelector("[data-ai-voice]").hidden = true;'), 'Assistant UI identifies the no-cost mode and keeps unsupported voice input hidden');
markazAiCheck(!str_contains($studentResultHtml, 'markaz-ai.js') && str_contains($studentResultHtml, 'student-result.js'), 'Conventional student result portal does not directly load the staff AI widget');
markazAiCheck(str_contains($widget, 'markaz-ai-launcher') && str_contains($widget, 'markaz-ai-chat'), 'AI assistant has an interactive shared UI');
markazAiCheck(str_contains($widget, '/api/ai/context.php') && str_contains($widget, 'selectedScope'), 'Existing teacher assignment context remains available without exposing scan functionality');
markazAiCheck(str_contains($scanner, 'IMAGE_SCANNING_UNAVAILABLE_NO_COST_MODE') && !str_contains($scanner, 'api.openai.com') && !str_contains($scanner, 'input_image'), 'Result-sheet scanning cannot call a paid image model in no-cost mode');
markazAiCheck(str_contains($resultSave, 'markazRecordActivity') && str_contains($resultSave, "'result_updated'"), 'Existing result save API records actor and result create/edit audit events');
markazAiCheck(str_contains($chat, 'markazAiRequireSameOrigin') && str_contains($history, 'account_id = ?'), 'AI write and read operations enforce same-origin and account ownership boundaries');
markazAiCheck(str_contains($audit, 'markaz_activity_events') && str_contains($audit, 'markaz_ai_audit_events'), 'Admin activity feed combines recorded school actions and AI events');
markazAiCheck(str_contains($migration, 'account_name_snapshot') && str_contains($migration, 'student_name_snapshot') && str_contains($migration, 'ON DELETE SET NULL'), 'Audit actor identity is retained when a staff or student account is deleted');
markazAiCheck(str_contains($attendanceSave, 'attendance_register_saved') && str_contains($attendanceSave, 'markazRecordActivity'), 'Attendance register saves are recorded for Admin school-wide oversight');
markazAiCheck(str_contains($styles, '@media') && str_contains($styles, 'prefers-reduced-motion'), 'AI assistant UI is responsive and respects reduced motion');


$onlineStudentPortal = file_get_contents(__DIR__ . '/../public/online-class-portal.html');
$studentContextApi = file_get_contents(__DIR__ . '/../api/ai/student-context.php');
$studentConversationsApi = file_get_contents(__DIR__ . '/../api/ai/student-conversations.php');
$studentTranscribeApi = file_get_contents(__DIR__ . '/../api/ai/student-transcribe.php');
$studentHistoryApi = file_get_contents(__DIR__ . '/../api/ai/student-history.php');
$studentDeleteApi = file_get_contents(__DIR__ . '/../api/ai/student-delete-conversation.php');
$deleteConversation = file_get_contents(__DIR__ . '/../api/ai/delete-conversation.php');
markazAiCheck(str_contains($helper, 'function markazAiIsStaffRole') && str_contains($helper, "['admin', 'exam_officer', 'teacher', 'management']"), 'Only Admin, Exam Officer, Teacher and Management are permitted staff roles');
markazAiCheck(str_contains($helper, 'if (!markazAiIsStaffRole($role))'), 'Server-side scope resolver denies all non-staff roles');
markazAiCheck(!str_contains($widget, 'student-context.php') && !str_contains($widget, 'student-chat.php') && str_contains($widget, '["admin","exam_officer","teacher","management"]'), 'Browser assistant mounts only after a verified staff role response');
markazAiCheck(str_contains($widget, 'get("student_portal") === "1")'), 'Student Portal route is denied even if a staff session cookie remains in the browser');
markazAiCheck(!str_contains($onlineStudentPortal, 'markaz-ai.js'), 'Online Class student portal does not load the AI widget');
foreach ([$studentChat, $studentContextApi, $studentConversationsApi, $studentTranscribeApi, $studentHistoryApi, $studentDeleteApi] as $studentAiEndpoint) {
    markazAiCheck(str_contains($studentAiEndpoint, 'STUDENT_AI_DISABLED') && str_contains($studentAiEndpoint, 'http_response_code(403)'), 'Every direct student AI endpoint rejects access with HTTP 403');
}
markazAiCheck(str_contains($deleteConversation, 'markazAiResolveScope(db()'), 'Conversation deletion also enforces the authorised staff role');
markazAiCheck(str_contains($helper, 'teacher_activity_today') && str_contains($helper, 'teacher_activity_truncated'), 'Admin teacher summaries are sourced from bounded, recorded daily activity');
markazAiCheck(str_contains($helper, 'Recorded MARKAZ activity for') && str_contains($helper, 'This summary covers recorded MARKAZ activity only'), 'Teacher activity summaries clearly distinguish recorded actions from unlogged work');

markazAiCheck(str_contains($widget, 'tesseract.js@5/dist/tesseract.min.js') && str_contains($widget, 'Tesseract.createWorker("eng"'), 'Result-sheet OCR runs in a browser worker without a paid AI provider');
markazAiCheck(str_contains($widget, 'worker.recognize(file)') && str_contains($widget, 'Image processed in this browser. It was not uploaded to MARKAZ.'), 'OCR processes the selected image locally and reports that no result data was saved');
markazAiCheck(str_contains($widget, 'NOTHING HAS BEEN SAVED') && str_contains($widget, 'manual step'), 'OCR output is explicitly review-only and cannot silently save result marks');

echo "MARKAZ AI security regression checks passed.\n";
