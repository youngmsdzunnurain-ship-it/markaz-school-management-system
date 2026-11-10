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

if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    http_response_code(413);
    echo json_encode([
        'success' => false,
        'message' => 'The selected image is larger than the server upload limit. Choose a smaller image.'
    ]);
    exit;
}
$email = trim($_POST['email'] ?? '');

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

$identityChanged = $name !== (string) $admin['name']
    || $email !== (string) ($admin['email'] ?? '');

if ($profileRole !== 'management' && $identityChanged && $currentPassword === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Current password is required when changing your name or email.'
    ]);
    exit;
}

if ($profileRole !== 'management' && $identityChanged && !password_verify($currentPassword, $admin['password_hash'])) {
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
        $uploadErrorMessage = match ((int) $file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The selected image exceeds the server upload limit. Choose a smaller image.',
            UPLOAD_ERR_PARTIAL => 'The image upload was interrupted. Please select the image again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload temporary folder is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded image.',
            UPLOAD_ERR_EXTENSION => 'The server blocked this image upload.',
            default => 'Profile image upload failed. Please try another image.'
        };
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => $uploadErrorMessage
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

    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to create the profile image directory.'
        ]);
        exit;
    }

    // Profile images are served directly by the web server as public avatars.
    // The directory and image therefore need read/execute permissions for it.
    @chmod($uploadDirectory, 0755);
    if (!is_writable($uploadDirectory)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'The profile image directory is not writable by the application.'
        ]);
        exit;
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

    @chmod($destination, 0644);
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
