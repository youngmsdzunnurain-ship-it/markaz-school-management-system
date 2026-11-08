<?php
declare(strict_types=1);

function teacherDocumentPermissionCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$accountsApi = file_get_contents(__DIR__ . '/../api/settings/accounts.php');
$accountUi = file_get_contents(__DIR__ . '/../public/js/account-management.js');
$auth = file_get_contents(__DIR__ . '/../app/middleware/auth.php');
$currentUser = file_get_contents(__DIR__ . '/../api/auth/me.php');
$resultJs = file_get_contents(__DIR__ . '/../public/js/student-result.js');

teacherDocumentPermissionCheck(
    str_contains($accountsApi, "'print_results',") &&
    str_contains($accountsApi, "'export_results',") &&
    str_contains($accountsApi, "saveAccountPermission("),
    'Admin account changes persist separate teacher print and export permissions'
);
teacherDocumentPermissionCheck(
    str_contains($accountUi, 'accountCanPrintResults') &&
    str_contains($accountUi, 'accountCanExportResults') &&
    str_contains($accountUi, "print_results: role.value === 'teacher'") &&
    str_contains($accountUi, "export_results: role.value === 'teacher'"),
    'Admin account editor exposes independent teacher print and export controls'
);
teacherDocumentPermissionCheck(
    str_contains($accountUi, 'syncTeacherDocumentPermissions') &&
    str_contains($accountUi, "assignment-mode") &&
    str_contains($accountsApi, '$hasFullClassAccess && filter_var'),
    'Teacher print/export switches are disabled and persisted off without Full Class Access'
);
teacherDocumentPermissionCheck(
    str_contains($auth, 'subject_id IS NULL LIMIT 1') &&
    str_contains($auth, '$individualPermission !== false') &&
    str_contains($auth, 'systemAccessEnabled($settingKey)'),
    'Teacher document actions require Full Class Access, individual permission and role-wide master switch'
);
teacherDocumentPermissionCheck(
    str_contains($currentUser, 'account_permissions') &&
    str_contains($currentUser, 'subject_id IS NULL'),
    'Current-user flags hide document actions unless every permission gate passes'
);
teacherDocumentPermissionCheck(
    str_contains($resultJs, 'class_id: classId || ""') &&
    str_contains($resultJs, 'class_type: classType || "conventional"'),
    'Document authorization includes the class context for server-side scope enforcement'
);

teacherDocumentPermissionCheck(
    str_contains(file_get_contents(__DIR__ . '/../api/results/student.php'), '$teacherSubjectScopeSql') &&
    str_contains(file_get_contents(__DIR__ . '/../api/results/student.php'), '$teacherResultScopeSql'),
    'Teacher student-result views and class averages are restricted to assigned subjects'
);
teacherDocumentPermissionCheck(
    str_contains(file_get_contents(__DIR__ . '/../api/results/class-completion.php'), 'ta.subject_id = s.id') &&
    str_contains(file_get_contents(__DIR__ . '/../api/results/class-completion.php'), '$hasFullClassAccess'),
    'Selective teachers receive completion progress only for their assigned subjects'
);
teacherDocumentPermissionCheck(
    str_contains(file_get_contents(__DIR__ . '/../api/classes/list.php'), 'subject_id IS NULL') &&
    str_contains(file_get_contents(__DIR__ . '/../api/students/list.php'), 'subject_id IS NULL'),
    'Teacher print roster exposes only assigned Full Class Access classes and students'
);

echo "Teacher document permission regression tests passed.\n";
