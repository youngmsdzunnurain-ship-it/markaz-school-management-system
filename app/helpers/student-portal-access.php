<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/session.php';

/**
 * Return the narrowly scoped public-student result grant for this request.
 * The grant is created only after lookup verifies the student, conventional
 * class, selected academic period, and school result-release status.
 */
function studentPortalResultScope(bool $requireQueryMatch = true): ?array
{
    // Only explicitly marked Student Portal requests may use the isolated student session.
    // A leftover student cookie must never shadow a signed-in staff session.
    if (($_GET['student_portal'] ?? '') !== '1') {
        return null;
    }

    // Do not start a student session on ordinary staff requests.
    if (empty($_COOKIE['MARKAZ_STUDENT_SESSION'])) {
        return null;
    }

    startStudentPortalSession();
    $scope = $_SESSION['student_portal_result_access'] ?? null;

    if (!is_array($scope)) {
        session_write_close();
        return null;
    }

    if ((int) ($scope['expires_at'] ?? 0) < time()) {
        unset($_SESSION['student_portal_result_access']);
        session_write_close();
        return null;
    }

    if (!$requireQueryMatch) {
        return $scope;
    }

    $studentId = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);
    $classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
    $classType = (string) ($_GET['class_type'] ?? 'conventional');

    if (
        !$studentId || !$classId ||
        (int) ($scope['student_id'] ?? 0) !== (int) $studentId ||
        (int) ($scope['class_id'] ?? 0) !== (int) $classId ||
        ($scope['class_type'] ?? '') !== $classType ||
        $classType !== 'conventional'
    ) {
        session_write_close();
        return null;
    }

    return $scope;
}
