<?php
declare(strict_types=1);

function deviceActivityAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
}

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);
    deviceActivityAssert(is_string($content), "{$path} exists");
    return $content;
};

$migration = $read('database/migrations/20261109_account_device_activity.sql');
$activityApi = $read('api/settings/account-device-activity.php');
$deviceSettingsApi = $read('api/settings/account-devices.php');
$activityScript = $read('public/js/device-activity.js');
$settingsScript = $read('public/js/settings.js');
$settingsNav = $read('public/js/settings-panel-navigation.js');
$settingsHtml = $read('public/settings.html');
$settingsCss = $read('public/css/account-devices.css');
$staffLogin = $read('api/auth/login.php');
$authMiddleware = $read('app/middleware/auth.php');
$studentPortalApi = $read('api/results/student-portal.php');
$studentPortalScript = $read('public/js/student-results-portal.js');
$studentLoginHtml = $read('public/student-login.html');
$studentReportHtml = $read('public/student-result-print.html');
$resultScript = $read('public/js/student-result.js');

deviceActivityAssert(
    str_contains($migration, "'staff'") && str_contains($migration, "'student'"),
    'device activity schema supports both staff and student identities'
);
deviceActivityAssert(
    str_contains($migration, 'total_active_seconds') &&
    str_contains($migration, 'duration_seconds') &&
    str_contains($migration, 'client_session_id'),
    'device activity stores accumulated active time in persistent session records'
);
deviceActivityAssert(
    str_contains($activityApi, 'studentPortalResultScope()') &&
    str_contains($activityApi, 'requireLogin()') &&
    str_contains($activityApi, 'duration_seconds') &&
    str_contains($activityApi, 'session_hash'),
    'activity endpoint derives identity from authenticated staff or verified student sessions and updates server-calculated duration'
);
deviceActivityAssert(
    str_contains($activityApi, "'clear_history'") &&
    str_contains($activityApi, 'requireSeniorAdmin'),
    'only Senior Admin can clear retained activity history'
);
deviceActivityAssert(
    str_contains($activityScript, 'visibilitychange') &&
    str_contains($activityScript, 'sendBeacon') &&
    str_contains($activityScript, 'heartbeat'),
    'browser activity tracking counts active visible usage and closes or pauses sessions'
);
deviceActivityAssert(
    str_contains($settingsScript, 'markazActivityRows') &&
    str_contains($settingsScript, 'total_active_seconds') &&
    str_contains($settingsScript, 'Clear Activity History'),
    'Account Devices shows usage duration and gives the Admin a clear-history action'
);
deviceActivityAssert(
    str_contains($settingsNav, 'account-devices') &&
    str_contains($settingsNav, 'Account Devices'),
    'Account Devices appears as a first-class grouped settings category'
);
deviceActivityAssert(
    str_contains($settingsHtml, 'account-devices.css'),
    'Account Devices uses a dedicated maintained stylesheet'
);
deviceActivityAssert(
    str_contains($settingsCss, '.account-devices-card') &&
    str_contains($settingsCss, '@media') &&
    str_contains($settingsCss, ':focus-visible'),
    'Account Devices styling is responsive, accessible and not dependent on inline overrides'
);
deviceActivityAssert(
    str_contains($studentPortalApi, 'student_id') &&
    str_contains($studentPortalScript, 'student_portal') &&
    str_contains($studentLoginHtml, 'device-activity.js') &&
    str_contains($studentReportHtml, 'device-activity.js'),
    'student result lookups and official report viewing are included in device activity tracking'
);
deviceActivityAssert(
    str_contains($staffLogin, 'This account has been blocked.') &&
    str_contains($authMiddleware, 'This account has been blocked.') &&
    !str_contains($staffLogin, 'The account can still be used from another device.'),
    'blocked-account messaging does not disclose the blocking administrator or suggest a device bypass'
);
deviceActivityAssert(
    str_contains($resultScript, 'printStudentResultButton?.remove()') &&
    str_contains($resultScript, 'downloadStudentPdfButton?.remove()'),
    'student result portal removes unavailable Print and Download PDF actions from the DOM'
);

echo "Account device activity, security messaging and result action regression checks passed." . PHP_EOL;
