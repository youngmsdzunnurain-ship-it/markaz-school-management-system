<?php
declare(strict_types=1);

function attendancePrintCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$page = file_get_contents(__DIR__ . '/../public/attendance.html');
$script = file_get_contents(__DIR__ . '/../public/js/attendance.js');
$style = file_get_contents(__DIR__ . '/../public/css/attendance.css');
$readme = file_get_contents(__DIR__ . '/../README.md');

attendancePrintCheck(str_contains($page, 'id="attendancePrintSheet"'), 'daily attendance page provides a dedicated print-only register');
attendancePrintCheck(str_contains($script, 'function renderAttendancePrintSheet') && str_contains($script, 'window.addEventListener("beforeprint"') && str_contains($script, 'window.addEventListener("afterprint"'), 'print flow builds a fresh register and restores screen state after printing');
attendancePrintCheck(str_contains($script, 'attendancePrintSheet') && str_contains($script, 'state.students') && str_contains($script, 'state.periods'), 'print output uses the loaded class roster, attendance marks and academic period');
attendancePrintCheck(str_contains($style, '@page') && str_contains($style, 'A4 portrait') && str_contains($style, '#attendancePrintSheet') && str_contains($style, '--attendance-print-zoom'), 'print stylesheet isolates the register and fits it to one A4 page');
attendancePrintCheck(str_contains($style, 'grid-template-columns:repeat(2,minmax(0,1fr))') && str_contains($style, 'attendance-print-column'), 'print register uses two balanced columns to fit the full class roster');
attendancePrintCheck(str_contains($readme, '## Overview') && str_contains($readme, '## Features') && str_contains($readme, '## Architecture') && str_contains($readme, '## Database and migrations') && str_contains($readme, '## Testing') && str_contains($readme, '## Deployment'), 'README documents system purpose, modules, architecture, database, tests and deployment');
attendancePrintCheck(str_contains($readme, 'Online Class') && str_contains($readme, 'Conventional') && str_contains($readme, 'Arabic') && str_contains($readme, 'attendance') && str_contains($readme, 'result'), 'README covers the core academic, attendance and Online Class workflows');

echo "Attendance one-page print and README coverage checks passed.\n";
