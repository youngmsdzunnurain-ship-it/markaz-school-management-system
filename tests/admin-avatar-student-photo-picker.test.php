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
$dashboardCss = file_get_contents($root . '/public/css/dashboard.css');
$studentsHtml = file_get_contents($root . '/public/students.html');
$studentsJs = file_get_contents($root . '/public/js/students.js');

studentPhotoAssert(is_string($dashboardCss), 'dashboard stylesheet exists');
studentPhotoAssert(is_string($studentsHtml), 'students page exists');
studentPhotoAssert(is_string($studentsJs), 'students page script exists');

$avatarRuleStart = strpos($dashboardCss, '/* MARKAZ — FINAL ADMIN PROFILE PHOTO */');
$avatarRuleEnd = strpos($dashboardCss, '/* MARKAZ — ADMIN INITIALS FALLBACK */', $avatarRuleStart ?: 0);
studentPhotoAssert($avatarRuleStart !== false && $avatarRuleEnd !== false, 'final admin avatar override is present');
$avatarRules = substr($dashboardCss, $avatarRuleStart, $avatarRuleEnd - $avatarRuleStart);
studentPhotoAssert(str_contains($avatarRules, 'width: 64px !important;'), 'dashboard admin profile avatar uses the established 64px size');
studentPhotoAssert(str_contains($avatarRules, 'height: 64px !important;'), 'dashboard admin profile avatar remains square');
studentPhotoAssert(str_contains($avatarRules, 'border-radius: 50% !important;'), 'dashboard admin profile avatar remains circular');
studentPhotoAssert(!str_contains($avatarRules, 'height: 104px'), 'old stretched portrait avatar override is removed');

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
