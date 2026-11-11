<?php
declare(strict_types=1);
function staffProfileMotionAssert(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
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
staffProfileMotionAssert(str_contains($profileApi, '$currentPassword === \'\'') && !str_contains($profileApi, "$profileRole !== 'management' && $identityChanged && $currentPassword === ''"), 'every profile update requires current-password confirmation, including Management');
staffProfileMotionAssert(str_contains($html, 'staffPhotoCropModal') && str_contains($settingsJs, 'staffPhotoCropCanvas'), 'staff profile image selection uses a square crop dialog');
staffProfileMotionAssert(str_contains($navigation, 'new Set(["profile"])') && str_contains($navigation, '"teacher"'), 'Management and Teacher receive profile-only settings, not school configuration');
staffProfileMotionAssert(str_contains($shellJs, '"teacher"') && str_contains($shellJs, 'settingsAllowed'), 'teacher profile settings link is enabled in the shared management shell');
staffProfileMotionAssert(str_contains($dashboardHtml, 'data-role-access="staff_profile"'), 'dashboard Settings navigation is visible to all staff roles');
staffProfileMotionAssert(!str_contains($dashboardHtml, '<span>System Settings</span>'), 'duplicate System Settings quick shortcut is removed');
staffProfileMotionAssert(str_contains($dashboardHtml, 'welcome-cap') && str_contains($dashboardCss, '@keyframes welcome-cap-sway'), 'shared staff greeting restores the animated gold graduation cap');
staffProfileMotionAssert(str_contains($dashboardJs, 'data-mk-dashboard-reveal') && str_contains($dashboardCss, 'mk-dashboard-motion-ready'), 'dashboard sections and KPI cards use progressive scroll motion');
staffProfileMotionAssert(str_contains($dashboardJs, 'profileButton.setAttribute("aria-expanded", "false")') && str_contains($dashboardJs, 'dropdown.hidden = true'), 'avatar dropdown starts closed and closes accessibly');
echo "Staff profile crop and dashboard motion regression checks passed (12 checks).\n";
