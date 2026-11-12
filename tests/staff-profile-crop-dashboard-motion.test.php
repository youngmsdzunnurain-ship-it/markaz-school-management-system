<?php
declare(strict_types=1);
function staffProfileMotionAssert(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\\n"); exit(1); }
}
$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$html = $read('public/settings.html');
$settingsJs = $read('public/js/settings.js');
$navigation = $read('public/js/settings-panel-navigation.js');
$profileApi = $read('api/settings/admin-profile.php');
$shellJs = $read('public/js/management-shell.js');
$dashboardHtml = $read('public/dashboard.html');
$dashboardJs = $read('public/js/dashboard.js');
$dashboardCss = $read('public/css/dashboard-2026.css');

staffProfileMotionAssert(str_contains($html, 'data-role-access="staff_profile"'), 'Settings page is available to signed-in staff profiles');
staffProfileMotionAssert(str_contains($settingsJs, '"teacher"') && str_contains($navigation, '"teacher"'), 'Teacher can access the profile-only Settings view');
staffProfileMotionAssert(str_contains($profileApi, "'teacher'") && str_contains($profileApi, 'password_verify($currentPassword'), 'profile API accepts teachers and verifies their current password');
staffProfileMotionAssert(str_contains($profileApi, '$currentPassword === \'\''), 'every profile update requires current-password confirmation, including Management');
staffProfileMotionAssert(str_contains($html, 'staffPhotoCropModal') && str_contains($settingsJs, 'staffPhotoCropCanvas'), 'staff profile image selection uses a square crop dialog');
staffProfileMotionAssert(str_contains($navigation, 'new Set(["profile"])') && str_contains($navigation, '"teacher"'), 'Management and Teacher receive profile-only settings, not school configuration');
staffProfileMotionAssert(str_contains($shellJs, '"management", "teacher"') && str_contains($shellJs, 'settingsAllowed'), 'teacher profile settings link is enabled in the shared management shell');
staffProfileMotionAssert(str_contains($dashboardHtml, 'data-role-access="staff_profile"'), 'dashboard Settings navigation is visible to all staff roles');
staffProfileMotionAssert(!str_contains($dashboardHtml, '<span>System Settings</span>'), 'duplicate System Settings quick shortcut is removed');
staffProfileMotionAssert(str_contains($dashboardHtml, 'welcome-cap') && str_contains($dashboardCss, '@keyframes welcome-cap-sway'), 'shared staff greeting restores the animated gold graduation cap');
staffProfileMotionAssert(str_contains($dashboardJs, 'data-mk-dashboard-reveal') && str_contains($dashboardCss, 'mk-dashboard-motion-ready'), 'dashboard reveal targets remain registered for layout stability');
staffProfileMotionAssert(str_contains($dashboardCss, 'opacity: 1 !important;') && str_contains($dashboardCss, 'filter: none !important;') && str_contains($dashboardCss, 'transition: none !important;'), 'dashboard entrance blur and fade are neutralized to prevent page-load shaking');
staffProfileMotionAssert(str_contains($dashboardJs, ':scope > .stats-grid > .stat-card') && str_contains($dashboardJs, '.dashboard-exam-section .progress-card') && str_contains($dashboardJs, '.dashboard-class-chart-card') && str_contains($dashboardJs, '.management-dashboard > article'), 'individual KPI, exam progress, attendance chart and Management profile cards receive staggered scroll reveals');
staffProfileMotionAssert(str_contains($dashboardCss, '.dashboard-attendance-category:hover') && str_contains($dashboardCss, '.dashboard-class-chart-card:hover') && str_contains($dashboardCss, '.management-dashboard-profile:hover'), 'attendance, analytics and staff profile cards receive smooth hover elevation');
staffProfileMotionAssert(str_contains($dashboardJs, 'profileButton.setAttribute("aria-expanded", "false")') && str_contains($dashboardJs, 'dropdown.hidden = true'), 'avatar dropdown starts closed and closes accessibly');
staffProfileMotionAssert(str_contains($settingsJs, 'window.markazSettingsAccountPromise') && str_contains($navigation, 'window.markazSettingsAccountPromise'), 'Settings and its role navigation share one authenticated account lookup');
staffProfileMotionAssert(str_contains($navigation, 'adminModules') && !str_contains($html, 'js/account-management.js'), 'privileged settings modules load only after Admin role confirmation');
staffProfileMotionAssert(str_contains($html, 'Confirm your current password whenever you save profile changes') && str_contains($html, 'Password changes are handled separately.') && str_contains($html, 'name="current_password"') && str_contains($html, 'required'), 'profile photo and details require current-password confirmation without changing the password');
staffProfileMotionAssert(str_contains($dashboardJs, 'href="./settings.html#profile"') && str_contains($dashboardJs, 'Profile') && str_contains($dashboardJs, 'Settings'), 'avatar dropdown restores separate Profile and Settings actions');
echo "Staff profile crop and dashboard motion regression checks passed (16 checks).\\n";
