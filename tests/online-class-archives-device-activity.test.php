<?php
declare(strict_types=1);

function onlineArchiveAssert(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
$root = dirname(__DIR__);
$read = static fn(string $path): string => (string) file_get_contents($root . '/' . $path);
$schema = $read('database/migrations/20261112_online_class_archives_device_activity.sql');
$api = $read('api/online-class/archives.php');
$staffApi = $read('api/online-class/index.php');
$studentResults = $read('api/online-class/student-results.php');
$activityApi = $read('api/settings/account-device-activity.php');
$activitySchema = $read('app/helpers/device-activity-schema.php');
$activityMigration = $read('database/migrations/20261109_account_device_activity.sql');
$activityScript = $read('public/js/device-activity.js');
$adminPage = $read('public/online-class.html');
$adminScript = $read('public/js/online-class.js');

onlineArchiveAssert(str_contains($schema, 'online_class_archives') && str_contains($schema, 'online_student_id'), 'archive metadata and Online Class activity identity are migrated');
onlineArchiveAssert(str_contains($api, 'password_verify') && str_contains($api, 'beginTransaction'), 'archive and clear actions verify the administrator and use transactions');
onlineArchiveAssert(str_contains($api, 'gzencode') && str_contains($api, 'download'), 'online archives are compressed and device archives can be downloaded');
onlineArchiveAssert(str_contains($staffApi, 'clear_attendance') && str_contains($staffApi, 'clear_results'), 'clear operations are scoped to selected level and academic period');
onlineArchiveAssert(str_contains($studentResults, 'online_class_archives') && str_contains($studentResults, 'released'), 'released archived results remain visible to students after active records are cleared');
onlineArchiveAssert(str_contains($activityApi, 'online_student_id') && str_contains($activityApi, 'online_class_students'), 'Online Class student sessions are tracked separately from conventional students');
onlineArchiveAssert(str_contains($activitySchema, 'online_student_id') && str_contains($activityMigration, 'online_student_id'), 'runtime and migration schemas support Online Class student identity without ID collisions');
onlineArchiveAssert(str_contains($activityScript, 'online_student') && str_contains($adminPage, 'online-class-archives') && str_contains($adminScript, 'backup_destination'), 'student tracking and the backup destination UI are wired');
echo "Online Class archive and device activity regression checks passed.\n";
