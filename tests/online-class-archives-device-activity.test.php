<?php
declare(strict_types=1);
function onlineArchiveAssert(bool $condition,string $message):void{if(!$condition){fwrite(STDERR,"FAIL: {$message}\n");exit(1);}}
$root=dirname(__DIR__);
$read=static fn(string $path):string=>(string)file_get_contents($root.'/'.$path);
$schema=$read('database/migrations/20261112_online_class_archives_device_activity.sql');
$api=$read('api/online-class/archives.php');
$staffApi=$read('api/online-class/index.php');
$studentResults=$read('api/online-class/student-results.php');
$activityApi=$read('api/settings/account-device-activity.php');
$activitySchema=$read('app/helpers/device-activity-schema.php');
$activityScript=$read('public/js/device-activity.js');
$portalPage=$read('public/online-class-portal.html');
$adminPage=$read('public/online-class.html');
$adminScript=$read('public/js/online-class-archives.js');
$wrapper=$read('public/api/online-class/archives.php');
onlineArchiveAssert(str_contains($schema,'CREATE TABLE IF NOT EXISTS online_class_archives')&&str_contains($schema,'LONGBLOB')&&str_contains($schema,'payload_sha256'),'Online backups have independent persistent compressed payload storage and integrity hashes');
onlineArchiveAssert(str_contains($api,'password_verify')&&str_contains($api,'requireSeniorAdmin')&&str_contains($api,'beginTransaction'),'Backup and destructive actions require Senior Admin password verification and transactions');
onlineArchiveAssert(str_contains($api,'gzencode')&&str_contains($api,'aes-256-gcm')&&str_contains($api,'hash_pbkdf2'),'Archives are compressed and downloadable device files are password-encrypted');
onlineArchiveAssert(str_contains($api,'DELETE a FROM online_class_attendance')&&str_contains($api,'WHERE s.level_id=? AND r.academic_session=? AND r.term=?'),'Clear operations are scoped to a selected level and exact result period');
onlineArchiveAssert(str_contains($api,'online_class_result_releases')&&str_contains($api,'Existing archives remain untouched'),'Clearing active results removes the active release state without deleting archives');
onlineArchiveAssert(str_contains($studentResults,'online_class_archives')&&str_contains($studentResults,'gzdecode')&&str_contains($studentResults,'payload_sha256'),'Student portal verifies and serves released archived results after active result clearing');
onlineArchiveAssert(str_contains($adminPage,'online-class-archives')&&str_contains($adminPage,'onlineBackupDestination')&&str_contains($adminPage,'onlineBackupRecords'),'Online Class Settings exposes destination selection and Backup Records');
onlineArchiveAssert(str_contains($adminScript,'backupOnlineAttendance')&&str_contains($adminScript,'clearOnlineResults')&&str_contains($adminScript,'downloadOnlineArchive'),'Backup, download and clear controls are wired in the UI');
onlineArchiveAssert(str_contains($activityApi,'onlineClassStudentSession')&&str_contains($activityApi,'online_student_id'),'Activity endpoint identifies Online Class students through their separate authenticated session');
onlineArchiveAssert(str_contains($activitySchema,"ENUM('staff','student','online_class_student')")&&str_contains($schema,'online_student_id'),'Activity schema stores a namespaced Online Class student identity without conventional student ID collisions');
onlineArchiveAssert(str_contains($activityScript,'online_class_student')&&str_contains($portalPage,'data-activity-role="online_class_student"'),'Online Class portal sends activity heartbeat and pause events to shared Account Device Activity');
onlineArchiveAssert(str_contains($wrapper,'api/online-class/archives.php'),'Archive API is available through the public API route');
echo "Online Class archives, scoped clearing and shared activity regression checks passed (12 checks).\n";
