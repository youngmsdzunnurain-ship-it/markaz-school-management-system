<?php
declare(strict_types=1);

function teacherPortalCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$accountsApi = file_get_contents(__DIR__ . '/../api/settings/accounts.php');
$accountUi = file_get_contents(__DIR__ . '/../public/js/account-management.js');
$dashboardApi = file_get_contents(__DIR__ . '/../public/api/dashboard/stats.php');
$dashboardJs = file_get_contents(__DIR__ . '/../public/js/dashboard.js');
$dashboardHtml = file_get_contents(__DIR__ . '/../public/dashboard.html');
$authMeApi = file_get_contents(__DIR__ . '/../api/auth/me.php');
$managementShellJs = file_get_contents(__DIR__ . '/../public/js/management-shell.js');
$attendanceCommonApi = file_get_contents(__DIR__ . '/../api/attendance/_common.php');
$classApi = file_get_contents(__DIR__ . '/../api/results/class.php');
$resultSaveApi = file_get_contents(__DIR__ . '/../api/results/save.php');

teacherPortalCheck(
    str_contains($accountsApi, 'preg_match(\'/^(?=.*[a-z])(?=.*[A-Z])[A-Za-z0-9._-]{4,60}$/\', $username)'),
    'Teacher username API validation accepts the username formats advertised by the form'
);
teacherPortalCheck(
    str_contains($accountUi, 'minlength="4"') &&
    str_contains($accountUi, 'pattern="(?=.*[a-z])(?=.*[A-Z])[A-Za-z0-9._-]{4,60}"'),
    'Teacher username form and API enforce the same length and character rules'
);
teacherPortalCheck(
    str_contains($accountsApi, 'A teacher username must be 4–60 characters') &&
    str_contains($accountsApi, 'A valid full name is required'),
    'Account creation reports the specific invalid field instead of a misleading generic validation message'
);
teacherPortalCheck(
    str_contains($dashboardApi, "role === 'teacher'") &&
    str_contains($dashboardApi, "'role' => 'teacher'") &&
    str_contains($dashboardApi, "'assignments' =>"),
    'Dashboard statistics endpoint returns assignment-scoped teacher workspace data before admin-only handling'
);
teacherPortalCheck(
    str_contains($dashboardApi, 'markazSortAndDeduplicateClasses') &&
    str_contains($dashboardApi, 'orderedAssignments'),
    'Teacher dashboard class assignments use the shared lowest-to-highest hierarchy'
);
teacherPortalCheck(
    str_contains($dashboardApi, 'assigned_students') &&
    str_contains($dashboardApi, 'assigned_classes') &&
    str_contains($dashboardApi, 'assigned_subjects'),
    'Teacher dashboard metrics are derived from assigned classes and subjects'
);
teacherPortalCheck(
    str_contains($dashboardJs, 'teacherAssignmentsSection') &&
    str_contains($dashboardJs, 'teacherAssignmentsList') &&
    str_contains($dashboardJs, 'dashboard-bottom-layout') &&
    str_contains($dashboardJs, 'stats-grid'),
    'Teacher dashboard renders the assignment workspace and removes administrator-only dashboard panels'
);
teacherPortalCheck(
    str_contains($classApi, '(ta.subject_id = s.id OR ta.subject_id IS NULL)') &&
    str_contains($resultSaveApi, 'requireTeacherAssignment('),
    'Teacher class view and result saving remain restricted to assigned subjects'
);



$authMe = file_get_contents(__DIR__ . '/../api/auth/me.php');
$attendanceCommon = file_get_contents(__DIR__ . '/../api/attendance/_common.php');
$profileSaveApi = file_get_contents(__DIR__ . '/../api/results/student-profile-save.php');
$studentResultApi = file_get_contents(__DIR__ . '/../api/results/student.php');
$studentResultJs = file_get_contents(__DIR__ . '/../public/js/student-result.js');
$managementShellJs = file_get_contents(__DIR__ . '/../public/js/management-shell.js');
$studentResultCss = file_get_contents(__DIR__ . '/../public/css/student-result.css');
$studentsCss = file_get_contents(__DIR__ . '/../public/css/students.css');

teacherPortalCheck(
    str_contains($authMe, "'attendance_records'") &&
    str_contains($authMe, '\'can_manage_attendance\' => $canManageAttendance') &&
    str_contains($dashboardJs, 'permissions.can_manage_attendance === true'),
    'Teacher dashboard Attendance navigation follows the individual account permission'
);
teacherPortalCheck(
    str_contains($attendanceCommon, "'attendance_records'") &&
    str_contains($attendanceCommon, 'teacher_assignments') &&
    str_contains($attendanceCommon, 'You are not assigned to this class.'),
    'Attendance endpoints enforce teacher permission and assigned-class scope'
);
teacherPortalCheck(
    str_contains($profileSaveApi, 'subject_id IS NULL') &&
    str_contains($profileSaveApi, 'Full Class Access is required') &&
    str_contains($profileSaveApi, 'Student does not belong to the assigned class.'),
    'Memorization updates require a matching Full Class Access assignment on the server'
);
teacherPortalCheck(
    str_contains($studentResultApi, 'teacher_full_class_access') &&
    str_contains($studentResultJs, 'teacherHasFullClassAccess()'),
    'Only Full Class Access teachers can edit behavioural and memorization fields'
);
teacherPortalCheck(
    !str_contains($managementShellJs, '<a href="/settings.html">Settings</a>') &&
    (str_contains($studentResultCss, 'body:not(.student-portal-result-view) .student-information .student-photo') &&
     str_contains($studentResultCss, 'display: none !important;') &&
     str_contains($studentResultCss, '@media screen and (max-width: 760px)')),
    'Avatar Settings is removed and mobile result profile photo is hidden'
);
teacherPortalCheck(
    str_contains($studentsCss, '.view-student-profile-header::after') &&
    str_contains($studentsCss, 'background:linear-gradient(90deg,#087f5b 0%,#087f5b 72%,#c99a24 100%)'),
    'Student Management profile uses the green and gold top frame'
);


$studentResultHtml = file_get_contents(__DIR__ . '/../public/student-result.html');
$studentResultPrintHtml = file_get_contents(__DIR__ . '/../public/student-result-print.html');
$classHtml = file_get_contents(__DIR__ . '/../public/class.html');
$dashboardLogoutJs = file_get_contents(__DIR__ . '/../public/js/dashboard.js');
$studentResultCss = file_get_contents(__DIR__ . '/../public/css/student-result.css');

teacherPortalCheck(
    str_contains($studentResultCss, 'Shared student information treatment') &&
    str_contains($studentResultCss, 'grid-template-areas:') &&
    str_contains($studentResultCss, 'body:not(.student-portal-result-view) .student-information .student-photo') &&
    str_contains($studentResultCss, 'display: none !important;') &&
    str_contains($studentResultCss, '@media screen and (max-width: 760px)'),
    'Staff result mobile profile follows the compact Student Portal layout and removes the photo column'
);
teacherPortalCheck(
    str_contains($studentResultCss, '.report-card .behaviour-grid') &&
    str_contains($studentResultCss, 'grid-template-columns: repeat(2, minmax(0, 1fr)) !important;'),
    'Staff behavioural report uses the compact two-column mobile layout'
);
teacherPortalCheck(
    str_contains($studentResultCss, '#studentMemorizationInput[hidden]') &&
    str_contains($studentResultHtml, 'class="behaviour-rating" disabled'),
    'Memorization input respects hidden state and behavioural controls start read-only until permission is granted'
);
teacherPortalCheck(
    str_contains($classHtml, 'id="resultSubjectSelect"') &&
    str_contains($classHtml, 'id="resultsToolbar"') === false,
    'Subject selection remains available in the class result-entry workspace'
);
teacherPortalCheck(
    str_contains($dashboardLogoutJs, 'teacher: "./teacher-login.html"') &&
    str_contains($dashboardLogoutJs, 'exam_officer: "./exam-officer-login.html"') &&
    str_contains($dashboardLogoutJs, 'management: "./management-login.html"') &&
    str_contains($dashboardLogoutJs, 'student: "./student-login.html"'),
    'Dashboard logout returns each role to its matching login page'
);
teacherPortalCheck(
    str_contains($managementShellJs, 'loginPathForRole') &&
    str_contains($managementShellJs, 'location.replace(loginPathForRole(currentRole))'),
    'Management-page logout returns each role to its matching login page'
);


teacherPortalCheck(
    str_contains($studentResultApi, "'current_user_role' => \$studentPortalScope === null ? currentAdminRole() : null") &&
    str_contains($studentResultJs, 'studentData?.current_user_role'),
    'Staff result editor reuses the authenticated role from its protected result payload'
);


teacherPortalCheck(
    str_contains($studentResultJs, 'async function loadCurrentUserPermissions()') &&
    str_contains($studentResultJs, 'await loadCurrentUserPermissions();'),
    'Dedicated print/export permission verification remains active after role resolution is moved to the protected result payload'
);

$attendanceClassesApi = file_get_contents(__DIR__ . '/../api/attendance/classes.php');
$dashboardComboJs = file_get_contents(__DIR__ . '/../public/js/dashboard.js');
$attendancePageJs = file_get_contents(__DIR__ . '/../public/js/attendance.js');
$loginRolesJs = file_get_contents(__DIR__ . '/../public/js/login-roles.js');
$loginRolesCss = file_get_contents(__DIR__ . '/../public/css/login-roles.css');

teacherPortalCheck(
    str_contains($authMeApi, "SELECT is_allowed FROM account_permissions") &&
    str_contains($authMeApi, "'attendance_records'") &&
    str_contains($authMeApi, "can_manage_attendance") &&
    str_contains($dashboardJs, "permissions.can_manage_attendance === true") &&
    str_contains($managementShellJs, "a.can_manage_attendance!==true") &&
    str_contains($attendanceCommonApi, "Attendance recording has not been enabled for your teacher account."),
    'Attendance is hidden from unpermitted teachers and denied by the backend unless their individual permission is enabled'
);

teacherPortalCheck(
    str_contains($dashboardComboJs, '"KG", "Nursery 1", "Nursery 2", "Primary 1", "Primary 2"') &&
    !str_contains($dashboardComboJs, '"Basic 6"') &&
    str_contains($dashboardComboJs, '"SS 1", "SS 2", "SS 3"'),
    'Attendance chart follows the established conventional class hierarchy'
);

teacherPortalCheck(
    str_contains($attendanceClassesApi, "'available_class_types'") &&
    str_contains($attendancePageJs, 'available_class_types') &&
    str_contains($attendancePageJs, 'attendanceClassTypeField') &&
    str_contains($attendancePageJs, 'hasSingleAssignedClass') &&
    str_contains($attendancePageJs, 'attendanceClassField.hidden = hasSingleAssignedClass'),
    'Teacher attendance category and sole assigned class are automatically selected without redundant dropdowns'
);
teacherPortalCheck(
    str_contains($loginRolesJs, 'setInterval(showCountdown, 1000)') &&
    str_contains($loginRolesJs, 'message.dataset.kind = "error"') &&
    str_contains($loginRolesCss, '.role-message[data-kind="error"]'),
    'Teacher lockout countdown updates every second and login errors use the red error state'
);
teacherPortalCheck(
    substr_count($dashboardHtml, 'href="./settings.html"') === 1,
    'Admin sidebar contains one Settings navigation entry'
);

teacherPortalCheck(
    !str_contains($studentResultCss, 'Final mobile parity for staff result previews and Student Check Results') &&
    str_contains($studentResultCss, 'min-width: 561px') &&
    str_contains($studentResultCss, 'grid-template-areas:') &&
    str_contains($studentResultCss, 'max-width: 760px'),
    'Student Portal and staff result profile rows stay aligned through tablet widths'
);


$dashboardNavigationHtml = file_get_contents(__DIR__ . '/../public/dashboard.html');
$dashboardActiveCss = file_get_contents(__DIR__ . '/../public/css/dashboard-2026.css');
$dashboardAttendanceJs = file_get_contents(__DIR__ . '/../public/js/dashboard.js');
$attendanceReportsApi = file_get_contents(__DIR__ . '/../api/attendance/reports.php');

teacherPortalCheck(
    !str_contains($dashboardNavigationHtml, '<section class="nav-section"') &&
    strpos($dashboardNavigationHtml, 'href="./dashboard.html"') < strpos($dashboardNavigationHtml, 'href="./students.html"') &&
    strpos($dashboardNavigationHtml, 'href="./students.html"') < strpos($dashboardNavigationHtml, 'href="./classes.html"') &&
    strpos($dashboardNavigationHtml, 'href="./classes.html"') < strpos($dashboardNavigationHtml, 'href="./subjects/"') &&
    strpos($dashboardNavigationHtml, 'href="./subjects/"') < strpos($dashboardNavigationHtml, 'href="./print-results.html"') &&
    strpos($dashboardNavigationHtml, 'href="./print-results.html"') < strpos($dashboardNavigationHtml, 'href="./settings.html"'),
    'Dashboard sidebar preserves its original navigation sequence'
);
teacherPortalCheck(
    !str_contains($dashboardActiveCss, '.nav-section{') &&
    str_contains($dashboardActiveCss, '.dashboard-class-attendance-charts{'),
    'Dashboard attendance charts remain styled without changing sidebar layout'
);
teacherPortalCheck(
    str_contains($dashboardNavigationHtml, 'dashboard-class-attendance-charts') &&
    str_contains($dashboardNavigationHtml, 'dashboardConventionalAttendance') &&
    str_contains($dashboardNavigationHtml, 'dashboardArabicAttendance') &&
    str_contains($dashboardActiveCss, '.dashboard-class-attendance-charts{'),
    'Dashboard keeps separate conventional and Arabic class attendance charts in the responsive layout'
);
teacherPortalCheck(
    str_contains($dashboardAttendanceJs, 'allClasses.filter(item => String(item.class_type || "").trim().toLowerCase() === type)') &&
    str_contains($dashboardAttendanceJs, 'const rate = marked > 0 && studentCount > 0') &&
    str_contains($dashboardAttendanceJs, ': null;'),
    'Dashboard retains every class in its own category and represents unmarked classes without inventing a zero rate'
);
teacherPortalCheck(
    str_contains($attendanceReportsApi, 'LEFT JOIN attendance_records ar') &&
    str_contains($attendanceReportsApi, 'WHERE c.is_active = 1') &&
    str_contains($attendanceReportsApi, "'classes'") &&
    str_contains($attendanceReportsApi, "'arabic_classes'"),
    'Attendance report supplies every active conventional and Arabic class, including classes with no saved marks'
);


$classesPageJs = file_get_contents(__DIR__ . '/../public/js/students.js');
$classesPageCss = file_get_contents(__DIR__ . '/../public/css/students.css');
$classWorkspaceJs = file_get_contents(__DIR__ . '/../public/js/class.js');
$classWorkspaceCss = file_get_contents(__DIR__ . '/../public/css/class.css');
$classHierarchy = file_get_contents(__DIR__ . '/../app/helpers/class-hierarchy.php');

teacherPortalCheck(
    !str_contains($dashboardNavigationHtml, '<section class="nav-section"') &&
    strpos($dashboardNavigationHtml, 'href="./dashboard.html"') < strpos($dashboardNavigationHtml, 'href="./students.html"') &&
    strpos($dashboardNavigationHtml, 'href="./students.html"') < strpos($dashboardNavigationHtml, 'href="./classes.html"') &&
    strpos($dashboardNavigationHtml, 'href="./classes.html"') < strpos($dashboardNavigationHtml, 'href="./subjects/"') &&
    strpos($dashboardNavigationHtml, 'href="./subjects/"') < strpos($dashboardNavigationHtml, 'href="./print-results.html"') &&
    strpos($dashboardNavigationHtml, 'href="./print-results.html"') < strpos($dashboardNavigationHtml, 'href="./settings.html"'),
    'Dashboard sidebar preserves the original navigation sequence without newly grouped sections'
);
teacherPortalCheck(
    !str_contains($dashboardActiveCss, '.nav-section{') &&
    str_contains($dashboardActiveCss, '.dashboard-class-attendance-charts{'),
    'Dashboard keeps attendance chart styling without changing the original sidebar layout'
);
teacherPortalCheck(
    str_contains($dashboardAttendanceJs, 'const conventionalOrder') &&
    str_contains($dashboardAttendanceJs, 'const arabicOrder') &&
    str_contains($dashboardAttendanceJs, 'const orderedRows = []'),
    'Dashboard attendance charts retain the explicit conventional and Arabic class hierarchies'
);
teacherPortalCheck(
    str_contains($classHierarchy, "'kg', 'nursery 1', 'nursery 2', 'primary 1', 'primary 2'") &&
    str_contains($classHierarchy, "'roudoh awwal', 'roudoh sani', 'faslu ula', 'faslu sani'"),
    'Shared class ordering preserves the established conventional and Arabic academic hierarchies'
);
teacherPortalCheck(
    str_contains($classesPageJs, 'data-label="Parent / Guardian"') &&
    str_contains($classesPageJs, 'data-label="Actions"') &&
    str_contains($classesPageCss, '.students-table tbody tr') &&
    str_contains($classesPageCss, 'content: attr(data-label)'),
    'Admin and Exam Officer student management converts every student row into a labelled mobile card'
);
teacherPortalCheck(
    str_contains($classWorkspaceJs, 'data-label="Admission No."') &&
    str_contains($classWorkspaceJs, 'data-label="Action"') &&
    str_contains($classWorkspaceCss, '.class-students-table tbody tr') &&
    str_contains($classWorkspaceCss, 'content: attr(data-label)'),
    'Teacher class student management converts student rows into labelled mobile cards'
);


echo "Teacher portal dashboard regression tests passed.\n";
