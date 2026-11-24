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
$printPage = file_get_contents(__DIR__ . '/../public/student-result-print.html');
$settingsJs = file_get_contents(__DIR__ . '/../public/js/settings.js');
$settingsCss = file_get_contents(__DIR__ . '/../public/css/settings.css');

teacherDocumentPermissionCheck(
    str_contains($accountsApi, "'print_results',") &&
    str_contains($accountsApi, "saveAccountPermission("),
    'Admin account changes persist a single teacher print permission'
);
teacherDocumentPermissionCheck(
    str_contains($accountUi, 'accountCanPrintResults') &&
    str_contains($accountUi, "print_results: role.value === 'teacher'"),
    'Admin account editor exposes the teacher print control only'
);
teacherDocumentPermissionCheck(
    str_contains($accountUi, 'syncTeacherDocumentPermissions') &&
    str_contains($accountUi, "assignment-mode") &&
    str_contains($accountsApi, '$hasFullClassAccess && filter_var'),
    'Teacher print permission is disabled and persisted off without Full Class Access'
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
    str_contains(file_get_contents(__DIR__ . '/../api/classes/list.php'), '"arabic" AS class_type') &&
    str_contains(file_get_contents(__DIR__ . '/../api/students/list.php'), '"arabic" AS class_type') &&
    str_contains(file_get_contents(__DIR__ . '/../public/js/print-results.js'), 'frameParams.set("class_type", "arabic")'),
    'Full-class teacher printing preserves conventional and Arabic class separation'
);
teacherDocumentPermissionCheck(
    str_contains($resultJs, 'action: "print_results"') &&
    str_contains($resultJs, 'window.print();') &&
    !str_contains($resultJs, 'export_results') &&
    !str_contains($resultJs, 'downloadStudentPdfButton') &&
    !str_contains($printPage, 'downloadStudentPdfButton') &&
    !str_contains($printPage, 'Download PDF'),
    'Student reports use one permission-checked browser Print action; no separate PDF export or server document is offered'
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

teacherDocumentPermissionCheck(
    preg_match('/const roles = \\[\\s*\\["exam_officer", "Exam Officer"\\],\\s*\\["teacher", "Teacher"\\],\\s*\\["student", "Student"\\]\\s*\\];/s', $settingsJs) === 1,
    'Result printing settings show only Exam Officer, Teacher and Student; Admin and Management are excluded'
);
teacherDocumentPermissionCheck(
    str_contains($settingsJs, 'markaz-role-permissions-access-panel') &&
    str_contains($settingsJs, 'markaz-role-permissions-print-panel') &&
    str_contains($settingsCss, 'grid-template-columns: minmax(0, 1fr) minmax(0, 1fr)') &&
    str_contains($settingsCss, 'grid-template-columns: repeat(3, minmax(0, 1fr))'),
    'Portal opening and closing controls stay on the left while the three print permissions share the right column'
);
teacherDocumentPermissionCheck(
    str_contains($auth, '$role === \'admin\'') &&
    str_contains($auth, '$role === \'management\'') &&
    str_contains($currentUser, '$role === \'admin\'') &&
    str_contains($currentUser, '$role === \'management\''),
    'Admin printing remains superuser-enabled while Management printing is denied in both action and session permission checks'
);
teacherDocumentPermissionCheck(
    str_contains($accountsApi, '$accountRole === \'admin\'') &&
    str_contains($accountsApi, '$accountRole === \'management\''),
    'Account permission responses report Admin as always allowed and Management as never allowed to print'
);

teacherDocumentPermissionCheck(
    str_contains($settingsCss, 'grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr) !important') &&
    str_contains($settingsCss, 'grid-template-columns: repeat(3, minmax(0, 1fr)) !important'),
    'On narrow phones, account controls remain on the left and all three result-print roles remain in one row on the right'
);

echo "Teacher document permission regression tests passed.\n";
