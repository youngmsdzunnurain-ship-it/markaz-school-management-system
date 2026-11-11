<?php
declare(strict_types=1);

function studentPhotoAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$dashboardCss = file_get_contents($root . '/public/css/dashboard-2026.css');
$studentsHtml = file_get_contents($root . '/public/students.html');
$studentsJs = file_get_contents($root . '/public/js/students.js');

studentPhotoAssert(is_string($dashboardCss), 'dashboard stylesheet exists');
studentPhotoAssert(is_string($studentsHtml), 'students page exists');
studentPhotoAssert(is_string($studentsJs), 'students page script exists');

$avatarRuleStart = strpos($dashboardCss, '.admin-profile-photo img{');
studentPhotoAssert($avatarRuleStart !== false, 'active dashboard stylesheet defines the administrator profile image');
$avatarRules = substr($dashboardCss, $avatarRuleStart, 220);
studentPhotoAssert(str_contains($avatarRules, 'object-position:center center'), 'dashboard profile image uses centered cropping');
studentPhotoAssert(str_contains($avatarRules, 'object-fit:cover'), 'dashboard profile image fills its circular frame without distortion');
studentPhotoAssert(str_contains($dashboardCss, '.admin-profile-photo{grid-column:1;grid-row:1 / span 2;'), 'dashboard avatar remains vertically aligned beside the name and email rows');

foreach (['studentPhoto', 'editStudentPhoto'] as $inputId) {
    preg_match('/<input\\b(?=[^>]*id="' . preg_quote($inputId, '/') . '")[^>]*>/s', $studentsHtml, $match);
    studentPhotoAssert(isset($match[0]), "{$inputId} upload input exists");
    studentPhotoAssert(!str_contains($match[0], 'capture='), "{$inputId} normal upload does not force the camera");
}

foreach ([
    'id="studentPhotoCamera"',
    'id="editStudentPhotoCamera"',
    'id="chooseStudentPhotoButton"',
    'id="captureStudentPhotoButton"',
    'id="captureEditStudentPhotoButton"',
] as $required) {
    studentPhotoAssert(str_contains($studentsHtml, $required), "student photo picker markup includes {$required}");
}

foreach ([
    'studentPhotoCamera',
    'editStudentPhotoCamera',
    'new DataTransfer()',
    'dispatchEvent(new Event("change", { bubbles: true }))',
] as $required) {
    studentPhotoAssert(str_contains($studentsJs, $required), "student photo picker script includes {$required}");
}

echo "Admin avatar and student photo picker regression checks passed.\n";
