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
    (str_contains($studentResultCss, '.student-information .student-photo { display:none!important; }') ||
     str_contains($studentResultCss, '.student-information .student-photo { display: none !important; }')),
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
    str_contains($studentResultCss, '.student-information .student-photo { display:none!important; }'),
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

echo "Teacher portal dashboard regression tests passed.\n";
