<?php

declare(strict_types=1);

/**
 * Stores student photos in a directory for the active academic session.
 * Returned paths are relative to the public document root and safe to persist
 * in students.profile_image.
 */
function markazStoreStudentPhoto(PDO $db, array $upload): string
{
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Student photo upload failed.');
    }

    $tmpFile = (string) ($upload['tmp_name'] ?? '');
    $size = (int) ($upload['size'] ?? 0);
    if ($tmpFile === '' || $size < 1 || $size > 3 * 1024 * 1024 || !is_uploaded_file($tmpFile)) {
        throw new InvalidArgumentException('Student photo must be a valid image not exceeding 3MB.');
    }

    $imageInfo = @getimagesize($tmpFile);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
    if (!isset($allowedTypes[$mime])) {
        throw new InvalidArgumentException('Only JPG, PNG, and WebP images are allowed.');
    }

    $sessionStmt = $db->query(
        'SELECT id, name FROM academic_sessions WHERE is_current = 1 ORDER BY id DESC LIMIT 1'
    );
    $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        throw new RuntimeException('No current academic session is configured. Student photo was not saved.');
    }

    $sessionName = trim((string) ($session['name'] ?? ''));
    $sessionFolder = strtolower(trim(
        (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $sessionName),
        '-._'
    ));
    if ($sessionFolder === '') {
        $sessionFolder = 'session-' . (int) $session['id'];
    }

    $relativeDirectory = 'images/' . $sessionFolder;
    $publicRoot = dirname(__DIR__, 2) . '/public';
    $uploadDirectory = $publicRoot . '/' . $relativeDirectory;

    if (
        !is_dir($uploadDirectory)
        && !mkdir($uploadDirectory, 0755, true)
        && !is_dir($uploadDirectory)
    ) {
        throw new RuntimeException('Unable to prepare the academic-session photo directory.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
    $destination = $uploadDirectory . '/' . $fileName;
    if (!move_uploaded_file($tmpFile, $destination)) {
        throw new RuntimeException('Unable to save student photo.');
    }

    @chmod($destination, 0644);
    return $relativeDirectory . '/' . $fileName;
}
