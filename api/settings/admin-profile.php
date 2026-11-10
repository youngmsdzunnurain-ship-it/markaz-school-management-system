<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/config/app.php';
require_once __DIR__ . '/../../app/middleware/auth.php';

$profileRole = currentAdminRole();
if (!in_array($profileRole, ['admin', 'exam_officer', 'management'], true)) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Profile access is not available for this account.']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

$currentPassword = $_POST['current_password'] ?? '';
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');

if ($currentPassword === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Current password is required.'
    ]);
    exit;
}

if ($name === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Name is required.'
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);
    exit;
}

$db = db();

$stmt = $db->prepare(
    'SELECT id, name, email, password_hash, profile_image
     FROM admins
     WHERE id = ?
     LIMIT 1'
);

$stmt->execute([$_SESSION['admin_id']]);
$admin = $stmt->fetch();

if (!$admin) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Administrator account not found.'
    ]);
    exit;
}

if (!password_verify($currentPassword, $admin['password_hash'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Current password is incorrect.'
    ]);
    exit;
}

/*
 * Check whether another administrator already uses this email.
 */
$stmt = $db->prepare(
    'SELECT id FROM admins WHERE email = ? AND id != ? LIMIT 1'
);

$stmt->execute([$email, $admin['id']]);

if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'That email address is already in use.'
    ]);
    exit;
}

$profileImage = $admin['profile_image'];
$removeProfileImage = ($_POST['remove_profile_image'] ?? '') === '1';

/*
 * Profile picture is optional.
 *
 * Removing the picture takes priority over a newly selected file.
 */
if ($removeProfileImage) {
    $profileImage = null;
} elseif (
    isset($_FILES['profile_image']) &&
    $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
) {
    $file = $_FILES['profile_image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Profile image upload failed.'
        ]);
        exit;
    }

    if ($file['size'] > 3 * 1024 * 1024) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Profile image must not exceed 3MB.'
        ]);
        exit;
    }

    $imageInfo = @getimagesize($file['tmp_name']);

    if ($imageInfo === false) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'The selected file is not a valid image.'
        ]);
        exit;
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $mimeType = $imageInfo['mime'];

    if (!isset($allowedMimeTypes[$mimeType])) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Only JPG, PNG and WEBP images are allowed.'
        ]);
        exit;
    }

    $uploadDirectory = dirname(__DIR__, 2) . '/public/uploads/admins';

    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0700, true);
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
    $destination = $uploadDirectory . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to save the profile image on the server.'
        ]);
        exit;
    }

    $profileImage = 'uploads/admins/' . $filename;
}

$stmt = $db->prepare(
    'UPDATE admins
     SET name = ?, email = ?, profile_image = ?
     WHERE id = ?'
);

$stmt->execute([
    $name,
    $email,
    $profileImage,
    $admin['id']
]);

/*
 * Remove the previous stored image only after the database update
 * succeeds. Never delete files outside the admin upload directory.
 */
if ($removeProfileImage && !empty($admin['profile_image'])) {
    $oldRelativePath = ltrim(
        str_replace('\\', '/', $admin['profile_image']),
        '/'
    );

    if (strpos($oldRelativePath, 'uploads/admins/') === 0) {
        $oldFilename = basename($oldRelativePath);
        $oldFile = dirname(__DIR__, 2)
            . '/public/uploads/admins/'
            . $oldFilename;

        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }
}

$_SESSION['admin_name'] = $name;
$_SESSION['admin_email'] = $email;

echo json_encode([
    'success' => true,
    'message' => 'Profile updated successfully.',
    'admin' => [
        'id' => $admin['id'],
        'name' => $name,
        'email' => $email,
        'profile_image' => $profileImage
    ]
]);
