<?php
declare(strict_types=1);

function calendarCheck(bool $condition, string $message): void {
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
    echo "PASS: {$message}\n";
}

$root = dirname(__DIR__);
$api = file_get_contents($root . '/api/finance/academic-calendar.php');
$js = file_get_contents($root . '/public/js/student-fees-calendar.js');
$css = file_get_contents($root . '/public/css/student-fees-calendar.css');
$migration = file_get_contents($root . '/database/migrations/20261129_markaz_section_student_fees.sql');
$settings = file_get_contents($root . '/public/api/settings/academic-period.php');
$feesRoute = file_get_contents($root . '/public/api/finance/term-fees.php');
$calendarRoute = file_get_contents($root . '/public/api/finance/academic-calendar.php');
$settingsHtml = file_get_contents($root . '/public/settings.html');
$settingsJs = file_get_contents($root . '/public/js/settings.js');

calendarCheck(str_contains($migration, 'ADD COLUMN starts_on DATE NULL') && str_contains($migration, 'ADD COLUMN ends_on DATE NULL'), 'term date fields are additive and initially unset');
calendarCheck(str_contains($feesRoute, "require_once __DIR__ . '/../../../api/finance/term-fees.php'") && str_contains($calendarRoute, "require_once __DIR__ . '/../../../api/finance/academic-calendar.php'"), 'public routes forward Student Fees and calendar requests to their handlers');
calendarCheck(str_contains($settings, 'examination_date') && str_contains($settings, 'vacation_date') && str_contains($settings, 'starts_on') && str_contains($settings, 'ends_on'), 'Settings persists all Student Fees calendar dates');
calendarCheck(str_contains($settingsHtml, 'id="examinationDate"') && str_contains($settingsHtml, 'id="vacationDate"') && str_contains($settingsJs, 'examinationDateInput') && str_contains($settingsJs, 'vacationDateInput'), 'Settings exposes and loads examination and vacation dates');
calendarCheck(str_contains($api, 'academic_periods') && str_contains($api, 'examination_date') && str_contains($api, 'vacation_date') && str_contains($api, 'resumption_date'), 'calendar events come from saved academic period dates');
calendarCheck(str_contains($js, "./api/finance/academic-calendar.php") && str_contains($js, 'data-calendar-shift') && str_contains($js, 'data-calendar-date'), 'calendar loads live events and supports month navigation and date selection');
calendarCheck(str_contains($js, 'student-fees-calendar.css') && str_contains($css, '.student-fees-top-grid'), 'calendar has a responsive top-right Student Fees layout');
$financeJs = file_get_contents($root . '/public/js/finance.js');
calendarCheck(str_contains($financeJs, 'student-fees-calendar.js'), 'Finance loads the calendar on the Student Fees workspace');
calendarCheck(!preg_match('/202[0-9]-[0-9]{2}-[0-9]{2}/', $js), 'calendar contains no hardcoded academic dates');

echo "MARKAZ Student Fees calendar regression checks passed.\n";
