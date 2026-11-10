<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

header('Content-Type: application/json; charset=utf-8');

requireAdminOrManagementPermission('students');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$db = db();

do {
    $admissionNumber = 'MZ' . random_int(100000, 999999);

    $duplicateStmt = $db->prepare(
        'SELECT id FROM students WHERE admission_number = ? LIMIT 1'
    );
    $duplicateStmt->execute([$admissionNumber]);
} while ($duplicateStmt->fetch());

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

if ($firstName === '' || $lastName === '' || $classId <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'First name, last name, and conventional class are required.'
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

$classStmt = $db->prepare(
    'SELECT id FROM classes WHERE id = ? AND is_active = 1 LIMIT 1'
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
        'SELECT id FROM arabic_classes WHERE id = ? AND is_active = 1 LIMIT 1'
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
    'SELECT id FROM students WHERE admission_number = ? LIMIT 1'
);
$duplicateStmt->execute([$admissionNumber]);

if ($duplicateStmt->fetch()) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Admission number already exists.'
    ]);
    exit;
}

$profileImage = null;

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
    'INSERT INTO students (
        admission_number,
        first_name,
        middle_name,
        last_name,
        parent_guardian_name,
        parent_guardian_phone,
        profile_image,
        gender,
        student_type,
        date_of_birth,
        class_id,
        arabic_class_id,
        admission_date,
        is_active
     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
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
    $isActive
]);

echo json_encode([
    'success' => true,
    'message' => 'Student added successfully.',
    'student_id' => (int) $db->lastInsertId()
]);
