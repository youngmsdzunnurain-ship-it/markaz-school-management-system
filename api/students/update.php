<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$admissionNumber = trim($_POST['admission_number'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$parentGuardianName = trim($_POST['parent_guardian_name'] ?? '');
$parentGuardianPhone = trim($_POST['parent_guardian_phone'] ?? '');

if ($parentGuardianPhone !== '') {
    $phoneDigits = preg_replace('/\\D+/', '', $parentGuardianPhone);

    if (strlen($phoneDigits) === 13 && str_starts_with($phoneDigits, '234')) {
        $phoneDigits = substr($phoneDigits, 3);
    } elseif (strlen($phoneDigits) === 11 && str_starts_with($phoneDigits, '0')) {
        $phoneDigits = substr($phoneDigits, 1);
    }

    if (
        strlen($phoneDigits) !== 10 ||
        !preg_match('/^[789]\\d{9}$/', $phoneDigits)
    ) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Parent / Guardian phone must be a valid Nigerian mobile number.'
        ]);
        exit;
    }

    $parentGuardianPhone = '+234' . $phoneDigits;
}
$gender = trim($_POST['gender'] ?? '');
$studentType = trim($_POST['student_type'] ?? 'Day Student');
$dateOfBirth = trim($_POST['date_of_birth'] ?? '');
$classId = (int) ($_POST['class_id'] ?? 0);
$arabicClassId = (int) ($_POST['arabic_class_id'] ?? 0);
$admissionDate = trim($_POST['admission_date'] ?? '');
$isActive = (int) ($_POST['is_active'] ?? 1);

if ($id <= 0 || $admissionNumber === '' || $firstName === '' || $lastName === '' || $classId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Student, admission number, name, and conventional class are required.'
    ]);
    exit;
}

if ($gender !== '' && !in_array($gender, ['Male', 'Female'], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid gender.'
    ]);
    exit;
}

if (!in_array($studentType, ['Day Student', 'Boarding'], true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid student type.'
    ]);
    exit;
}

if (!in_array($isActive, [0, 1], true)) {
    $isActive = 1;
}

$db = db();

$studentStmt = $db->prepare(
    'SELECT id, profile_image
     FROM students
     WHERE id = ?
     LIMIT 1'
);
$studentStmt->execute([$id]);
$existingStudent = $studentStmt->fetch();

if (!$existingStudent) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Student not found.'
    ]);
    exit;
}

$classStmt = $db->prepare(
    'SELECT id
     FROM classes
     WHERE id = ? AND is_active = 1
     LIMIT 1'
);
$classStmt->execute([$classId]);

if (!$classStmt->fetch()) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Selected conventional class is invalid or inactive.'
    ]);
    exit;
}

if ($arabicClassId > 0) {
    $arabicStmt = $db->prepare(
        'SELECT id
         FROM arabic_classes
         WHERE id = ? AND is_active = 1
         LIMIT 1'
    );
    $arabicStmt->execute([$arabicClassId]);

    if (!$arabicStmt->fetch()) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Selected Arabic class is invalid or inactive.'
        ]);
        exit;
    }
} else {
    $arabicClassId = null;
}

$duplicateStmt = $db->prepare(
    'SELECT id
     FROM students
     WHERE admission_number = ?
       AND id <> ?
     LIMIT 1'
);
$duplicateStmt->execute([$admissionNumber, $id]);

if ($duplicateStmt->fetch()) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Admission number already exists.'
    ]);
    exit;
}

$profileImage = $existingStudent['profile_image'];

$removeProfileImage =
    isset($_POST['remove_profile_image']) &&
    $_POST['remove_profile_image'] === '1';

if ($removeProfileImage) {
    if (!empty($profileImage)) {
        $oldPhotoPath = dirname(__DIR__, 2) . '/public/' . ltrim($profileImage, '/');

        if (is_file($oldPhotoPath)) {
            @unlink($oldPhotoPath);
        }
    }

    $profileImage = null;
}

if (
    isset($_FILES['profile_image']) &&
    $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
) {
    if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Student photo upload failed.'
        ]);
        exit;
    }

    if ($_FILES['profile_image']['size'] > 3 * 1024 * 1024) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Student photo must not exceed 3MB.'
        ]);
        exit;
    }

    $tmpFile = $_FILES['profile_image']['tmp_name'];
    $imageInfo = @getimagesize($tmpFile);

    if ($imageInfo === false) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Uploaded file is not a valid image.'
        ]);
        exit;
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $mime = $imageInfo['mime'] ?? '';

    if (!isset($allowedTypes[$mime])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Only JPG, PNG, and WebP images are allowed.'
        ]);
        exit;
    }

    $uploadDirectory = __DIR__ . '/../../public/uploads/students';

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to prepare the student photo directory.']);
        exit;
    }
    @chmod($uploadDirectory, 0755);

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
    $destination = $uploadDirectory . '/' . $fileName;

    if (!move_uploaded_file($tmpFile, $destination)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to save student photo.'
        ]);
        exit;
    }

    @chmod($destination, 0644);
    $profileImage = 'uploads/students/' . $fileName;
}

$stmt = $db->prepare(
    'UPDATE students
     SET
        admission_number = ?,
        first_name = ?,
        middle_name = ?,
        last_name = ?,
        parent_guardian_name = ?,
        parent_guardian_phone = ?,
        profile_image = ?,
        gender = ?,
        student_type = ?,
        date_of_birth = ?,
        class_id = ?,
        arabic_class_id = ?,
        admission_date = ?,
        is_active = ?
     WHERE id = ?'
);

$stmt->execute([
    $admissionNumber,
    $firstName,
    $middleName !== '' ? $middleName : null,
    $lastName,
    $parentGuardianName !== '' ? $parentGuardianName : null,
    $parentGuardianPhone !== '' ? $parentGuardianPhone : null,
    $profileImage,
    $gender !== '' ? $gender : null,
    $studentType,
    $dateOfBirth !== '' ? $dateOfBirth : null,
    $classId,
    $arabicClassId,
    $admissionDate !== '' ? $admissionDate : null,
    $isActive,
    $id
]);

echo json_encode([
    'success' => true,
    'message' => 'Student updated successfully.'
]);
