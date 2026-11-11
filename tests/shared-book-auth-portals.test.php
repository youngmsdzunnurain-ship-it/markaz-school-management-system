<?php
declare(strict_types=1);

function bookAuthAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$pages = [
    'Admin' => 'login.html',
    'Teacher' => 'teacher-login.html',
    'Exam Officer' => 'exam-officer-login.html',
    'Management' => 'management-login.html',
    'Student Results' => 'student-login.html',
    'Online Class Student' => 'online-class-login.html',
];

foreach ($pages as $label => $file) {
    $html = file_get_contents($root . '/public/' . $file);
    bookAuthAssert(is_string($html), "{$label} login page exists");
    bookAuthAssert(str_contains($html, 'css/book-auth.css'), "{$label} uses the shared book stylesheet");
    bookAuthAssert(str_contains($html, 'js/book-auth.js'), "{$label} uses the shared book interaction script");
    foreach (['id="book"', 'id="formPage"', 'id="openBook"', 'id="mk-moon"'] as $required) {
        bookAuthAssert(str_contains($html, $required), "{$label} keeps required book structure: {$required}");
    }
    bookAuthAssert(!str_contains($html, '234XXXXXXXXXX'), "{$label} has no placeholder WhatsApp number");
}

$admin = file_get_contents($root . '/public/login.html');
bookAuthAssert(str_contains($admin, 'id="loginForm"') && str_contains($admin, 'id="email"') && str_contains($admin, 'id="password"') && str_contains($admin, 'id="loginBtn"'), 'Admin keeps the existing login form and credential selectors');
bookAuthAssert(strpos($admin, 'js/book-auth.js') < strpos($admin, 'js/login.js'), 'Admin loads book interactions before its existing login handler');
bookAuthAssert(str_contains($admin, 'id="remember"') && str_contains($admin, 'name="remember"'), 'Admin keeps the requested Remember me field');

$rolePages = [
    'teacher-login.html' => 'teacher',
    'exam-officer-login.html' => 'exam-officer',
    'management-login.html' => 'management',
];
foreach ($rolePages as $file => $role) {
    $html = file_get_contents($root . '/public/' . $file);
    bookAuthAssert(str_contains($html, 'data-role="' . $role . '"'), "{$role} keeps its role-specific form selector");
    bookAuthAssert(str_contains($html, 'class="role-message'), "{$role} keeps its existing message target");
    bookAuthAssert(str_contains($html, 'class="role-submit'), "{$role} keeps its existing submit target");
}

$student = file_get_contents($root . '/public/student-login.html');
foreach (['id="resultForm"', 'data-role="student-lookup"', 'id="studentId" name="student_id"', 'id="studentClass"', 'id="academicTerm"', 'id="academicSession"', 'id="resultBtn"', 'id="studentPortalExperience"'] as $required) {
    bookAuthAssert(str_contains($student, $required), "Student Results preserves {$required}");
}
$studentScript = file_get_contents($root . '/public/js/student-results-portal.js');
bookAuthAssert(str_contains($studentScript, "form.querySelector('#studentId')"), 'Student Results script uses the book design admission field');
bookAuthAssert(strpos($student, 'js/book-auth.js') < strpos($student, 'js/student-results-portal.js'), 'Student Results loads book interactions before its existing lookup handler');
bookAuthAssert(!str_contains($student, 'class-1'), 'Student Results does not use demonstration class options');

$online = file_get_contents($root . '/public/online-class-login.html');
foreach (['id="onlineLoginForm"', 'id="onlineAdmission"', 'id="onlineLevel"', 'id="onlineSession"', 'id="onlineTerm"', 'id="onlineLoginMessage"', 'id="onlineLoginButton"'] as $required) {
    bookAuthAssert(str_contains($online, $required), "Online Class preserves {$required}");
}
bookAuthAssert(strpos($online, 'js/book-auth.js') < strpos($online, 'js/online-class-login.js'), 'Online Class loads book interactions before its existing dynamic options and login handler');

$bookCss = file_get_contents($root . '/public/css/book-auth.css');
$bookJs = file_get_contents($root . '/public/js/book-auth.js');
bookAuthAssert(is_string($bookCss) && str_contains($bookCss, '.book.is-open'), 'Shared book stylesheet contains the open-book layout');
bookAuthAssert(is_string($bookJs) && str_contains($bookJs, 'lockForm'), 'Shared book script locks the closed form from keyboard and assistive technology');

$loginApi = file_get_contents($root . '/api/auth/login.php');
$studentApi = file_get_contents($root . '/api/results/student-portal.php');
$onlineApi = file_get_contents($root . '/api/online-class/student-login.php');
bookAuthAssert(str_contains($loginApi, 'expected_role') && str_contains($loginApi, 'password_verify'), 'Staff authentication keeps role checks and password verification');
bookAuthAssert(str_contains($studentApi, 'startStudentPortalSession'), 'Student result lookup keeps its scoped public session');
bookAuthAssert(str_contains($onlineApi, 'session_regenerate_id(true)') && str_contains($onlineApi, 'unset($_SESSION[\'admin_id\']'), 'Online Class authentication rotates the session ID and clears staff identity');

$officialPrint = file_get_contents($root . '/public/student-result-print.html');
$onlinePortal = file_get_contents($root . '/public/online-class-portal.html');
bookAuthAssert(!str_contains($officialPrint, 'css/book-auth.css'), 'Official student result and print page are not redesigned');
bookAuthAssert(!str_contains($onlinePortal, 'css/book-auth.css'), 'Authenticated Online Class results portal is not redesigned');

echo "Shared book authentication portal regression checks passed." . PHP_EOL;
