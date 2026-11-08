<?php
declare(strict_types=1);

function teacherDashboardAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$auth = file_get_contents($root . '/app/middleware/auth.php');
$classApi = file_get_contents($root . '/api/results/class.php');
$saveApi = file_get_contents($root . '/api/results/save.php');
$studentApi = file_get_contents($root . '/api/results/student.php');
$behaviourApi = file_get_contents($root . '/api/results/behavioural.php');
$completionApi = file_get_contents($root . '/api/results/class-completion.php');
$studentListApi = file_get_contents($root . '/api/students/list.php');
$studentProfileSaveApi = file_get_contents($root . '/api/results/student-profile-save.php');
$teacherStatsApi = file_get_contents($root . '/api/dashboard/teacher-stats.php');
$dashboard = file_get_contents($root . '/public/dashboard.html');
$dashboardJs = file_get_contents($root . '/public/js/dashboard.js');
$classJs = file_get_contents($root . '/public/js/class.js');

teacherDashboardAssert(
    str_contains($auth, 'function requireTeacherFullClassAssignment')
    && str_contains($auth, 'subject_id IS NULL'),
    'Full Class Access must be explicitly checked using the existing NULL-subject assignment'
);
teacherDashboardAssert(
    str_contains($saveApi, 'requireTeacherAssignment('),
    'score saves must continue enforcing the assigned class and subject server-side'
);
teacherDashboardAssert(
    str_contains($studentApi, 'requireTeacherFullClassAssignment($pdo, $classId, $classType)'),
    'subject-only teachers must not open unrestricted individual result pages by changing the URL'
);
teacherDashboardAssert(
    str_contains($behaviourApi, 'subject_id IS NULL')
    && str_contains($behaviourApi, 'requireFullClassBehaviourAccess'),
    'behavioural result access must remain restricted to Full Class Access'
);
teacherDashboardAssert(
    str_contains($classApi, 'teacher_access')
    && str_contains($classApi, 'ta.subject_id = s.id OR ta.subject_id IS NULL'),
    'class workspace must return only assigned subjects and the correct teacher access mode'
);
teacherDashboardAssert(
    str_contains($completionApi, 'AND s.id IN')
    && str_contains($completionApi, '$behaviourFields = [];'),
    'subject-only completion must include only assigned subjects and exclude behavioural fields'
);
teacherDashboardAssert(
    str_contains($studentListApi, 'requireAdmin();'),
    'teacher accounts must not access the general student-management roster API'
);
teacherDashboardAssert(
    str_contains($studentProfileSaveApi, 'requireSeniorAdmin();'),
    'teacher access must not permit editing student profile or memorization-portion fields'
);
teacherDashboardAssert(
    str_contains($teacherStatsApi, 'is_active = 1')
    && str_contains($teacherStatsApi, "'access_mode'"),
    'teacher dashboard must show only active assignments and label the assignment access mode'
);
teacherDashboardAssert(
    str_contains($dashboard, 'data-role-access="admin_or_exam_officer"')
    && str_contains($dashboardJs, 'role-teacher'),
    'teacher dashboard navigation must be limited to the Dashboard landing view'
);
teacherDashboardAssert(
    str_contains($classJs, 'if (isTeacher) {')
    && str_contains($classJs, 'editClassButton?.remove()')
    && str_contains($classJs, 'if (isTeacher && !teacherFullClassAccess) return;'),
    'teacher class workspace must remove profile-management actions and block subject-only student-result links'
);

echo "Teacher dashboard access regression tests passed.\n";
