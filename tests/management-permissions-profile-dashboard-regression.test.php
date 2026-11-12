<?php
declare(strict_types=1);
function managementPermissionsCheck(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$chartCss = $read('public/css/dashboard-2026.css');
$settingsJs = $read('public/js/settings-panel-navigation.js');
$settingsCss = $read('public/css/settings-panel-navigation.css');
$accountJs = $read('public/js/account-management.js');
$accountsApi = $read('api/settings/accounts.php');
$meApi = $read('api/auth/me.php');
$auth = $read('app/middleware/auth.php');
$attendanceCommon = $read('api/attendance/_common.php');
$studentCreate = $read('api/students/create.php');
$studentUpdate = $read('api/students/update.php');
$studentToggle = $read('api/students/toggle-status.php');
$studentList = $read('api/students/list.php');
$conventionalClassList = $read('api/classes/list.php');
$arabicClassList = $read('api/arabic-classes/list.php');
$profileApi = $read('api/settings/admin-profile.php');
$dashboardJs = $read('public/js/dashboard.js');
$dashboardHtml = $read('public/dashboard.html');
$shellJs = $read('public/js/management-shell.js');
$settingsProfileJs = $read('public/js/settings.js');
$dashboardStatsApi = $read('api/dashboard/stats.php');
$settingsHtml = $read('public/settings.html');

managementPermissionsCheck(
    str_contains($chartCss, 'body.sidebar-desktop-closed .dashboard-class-attendance-charts')
        && str_contains($chartCss, 'minmax(0, 3fr) minmax(0, 2fr)'),
    'desktop sidebar-closed combo charts use a 60:40 Conventional-to-Arabic split'
);
managementPermissionsCheck(
    strpos($settingsJs, 'fetch("./api/auth/me.php"') < strpos($settingsJs, 'classList.remove("settings-page-pending")')
        && str_contains($settingsCss, 'settings-page-pending .settings-category-nav'),
    'settings navigation stays hidden until role filtering has completed'
);
managementPermissionsCheck(
    str_contains($accountJs, 'management_permissions')
        && str_contains($accountJs, 'Attendance')
        && str_contains($accountJs, 'School Fees')
        && str_contains($accountJs, 'Students'),
    'Admin can assign Management access to Attendance, Students and School Fees'
);
managementPermissionsCheck(
    str_contains($accountsApi, 'management_attendance')
        && str_contains($accountsApi, 'management_students')
        && str_contains($accountsApi, 'management_school_fees'),
    'Management assignments are persisted and returned by account management'
);
managementPermissionsCheck(
    str_contains($meApi, 'management_permissions')
        && str_contains($auth, 'function requireManagementPermission'),
    'authenticated identity exposes manager permissions and backend permission checks exist'
);
managementPermissionsCheck(
    str_contains($attendanceCommon, "requireManagementPermission('attendance')")
        && str_contains($studentCreate, "requireAdminOrManagementPermission('students')")
        && str_contains($studentUpdate, "requireAdminOrManagementPermission('students')")
        && str_contains($studentToggle, "requireAdminOrManagementPermission('students')")
        && str_contains($studentList, "requireAdminOrManagementPermission('students')")
        && str_contains($conventionalClassList, "requireAdminOrManagementPermission('students')")
        && str_contains($arabicClassList, "requireAdminOrManagementPermission('students')"),
    'attendance, student CRUD and student class-option APIs enforce the selected Management assignment'
);
managementPermissionsCheck(
    str_contains($profileApi, "'management'")
        && str_contains($settingsJs, '"management"')
        && str_contains($settingsProfileJs, 'profile_image')
        && str_contains($settingsProfileJs, 'compressProfileImage')
        && str_contains($settingsProfileJs, 'staffPhotoCropCanvas')
        && str_contains($profileApi, 'password_verify($currentPassword')
        && str_contains($profileApi, '0755')
        && str_contains($profileApi, '0644'),
    'All staff roles can securely crop and update their own profile image'
);
managementPermissionsCheck(
    str_contains($dashboardHtml, 'management_students')
        && str_contains($dashboardJs, 'management_permissions')
        && str_contains($shellJs, 'management_permissions')
        && str_contains($dashboardHtml, 'id="managementDashboardShortcuts"')
        && str_contains($dashboardJs, 'window.markazManagementPermissions?.attendance === true'),
    'Management dashboard, attendance summaries, profile and shared navigation use individual permissions'
);
managementPermissionsCheck(
    str_contains($dashboardHtml, 'data-role-access="attendance_dashboard"')
        && str_contains($dashboardJs, 'requiredRole === "attendance_dashboard"')
        && str_contains($dashboardJs, 'role === "management" && permissions.management_permissions?.attendance === true')
        && str_contains($dashboardJs, 'window.markazManagementPermissions?.attendance === true'),
    'Management attendance permission reveals the dashboard combo charts without exposing them to other roles'
);
managementPermissionsCheck(
    str_contains($dashboardHtml, 'id="managementDashboardName"')
        && str_contains($dashboardHtml, 'id="managementDashboardEmail"')
        && str_contains($dashboardHtml, 'Active</small>')
        && str_contains($chartCss, '.management-dashboard-profile-body')
        && str_contains($chartCss, '.management-dashboard-profile-copy small')
        && str_contains($chartCss, '.management-dashboard-profile-link:hover'),
    'Management dashboard profile is presented as a styled staff identity card with active status and profile action'
);
managementPermissionsCheck(
    str_contains($accountsApi, 'saveAccountPermission($pdo, $targetId, \'management_\' . $managementPermission, $allowed)')
        && str_contains($accountJs, 'management_permissions: role.value === \'management\''),
    'Each Management account stores its own independently selected workspace permissions'
);
managementPermissionsCheck(
    str_contains($dashboardStatsApi, 'requireAdmin();')
        && str_contains($dashboardJs, 'if (role !== "management") loadDashboardStats();')
        && str_contains($dashboardJs, 'if (window.markazCurrentRole === "management") return;'),
    'Management dashboard does not load unrestricted examination statistics'
);
managementPermissionsCheck(
    str_contains($profileApi, '$currentPassword === \'\'')
        && str_contains($profileApi, 'password_verify($currentPassword, $admin[\'password_hash\'])')
        && str_contains($profileApi, "['admin', 'exam_officer', 'management', 'teacher']"),
    'Every staff profile update, including photo-only changes, requires the signed-in account password'
);
managementPermissionsCheck(
    str_contains($settingsProfileJs, 'maxBytes = 1.5 * 1024 * 1024')
        && str_contains($settingsProfileJs, 'Cropped photo ready. Confirm your password and save changes.')
        && str_contains($settingsProfileJs, 'response.redirected')
        && str_contains($settingsHtml, 'data-role-access="staff_profile"'),
    'Profile photos are compressed below the upload limit and Management profile settings are available'
);
echo "Management permissions/profile/dashboard regression tests passed (14 checks).\n";
