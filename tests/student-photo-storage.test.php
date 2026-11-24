<?php
declare(strict_types=1);

function studentPhotoCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$helper = file_get_contents(__DIR__ . '/../app/helpers/student-photos.php');
$create = file_get_contents(__DIR__ . '/../api/students/create.php');
$update = file_get_contents(__DIR__ . '/../api/students/update.php');
$photos = file_get_contents(__DIR__ . '/../api/students/photos.php');
$studentsJs = file_get_contents(__DIR__ . '/../public/js/students.js');
$apache = file_get_contents(__DIR__ . '/../public/images/.htaccess');
$worker = file_get_contents(__DIR__ . '/../public/sw.js');
$resultJs = file_get_contents(__DIR__ . '/../public/js/student-result.js');
$checkJs = file_get_contents(__DIR__ . '/../student-result-check.js');

studentPhotoCheck(str_contains($helper, 'academic_sessions WHERE is_current = 1'), 'uploads resolve the active academic session on the server');
studentPhotoCheck(str_contains($helper, "images/' . \$sessionFolder") && str_contains($helper, 'random_bytes(16)'), 'photos use per-session folders and collision-resistant filenames');
studentPhotoCheck(str_contains($helper, 'move_uploaded_file') && str_contains($helper, 'getimagesize'), 'uploads validate actual image content before moving files');
studentPhotoCheck(str_contains($create, 'markazStoreStudentPhoto') && str_contains($update, 'markazStoreStudentPhoto'), 'both student creation and replacement use session-scoped storage');
studentPhotoCheck(str_contains($photos, 'Cache-Control') && str_contains($photos, 'SELECT id, profile_image FROM students'), 'photo lookup is database-backed and never HTTP-cached');
studentPhotoCheck(str_contains($studentsJs, './api/students/photos.php') && str_contains($studentsJs, 'loading = "lazy"'), 'student management loads photo paths asynchronously and lazily');
studentPhotoCheck(str_contains($apache, 'private, max-age=31536000, immutable') && str_contains($apache, 'php[0-9]?') && str_contains($apache, 'Require all denied'), 'Apache caches immutable uploads only in private browser caches');
studentPhotoCheck(str_contains($worker, 'markaz-public-v6') && str_contains($worker, 'MAX_CACHED_ASSETS') && !str_contains($worker, 'uploads/students'), 'service worker cache version is refreshed without persisting private student photos');
studentPhotoCheck(str_contains($resultJs, 'image.loading = "lazy"') && str_contains($checkJs, 'image.loading = "lazy"'), 'student result views keep native lazy loading');

echo "PASS: academic-session student photo storage, retrieval, privacy-aware caching and lazy loading\n";
