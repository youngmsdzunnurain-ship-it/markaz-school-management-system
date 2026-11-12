<?php
declare(strict_types=1);

function websiteImageStorageAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

require_once __DIR__ . '/../app/helpers/website-image-storage.php';

$directory = sys_get_temp_dir() . '/markaz-image-storage-' . bin2hex(random_bytes(6));
if (!mkdir($directory, 0700, true)) {
    throw new RuntimeException('Could not create temporary test directory.');
}
$manifestPath = $directory . '/website-image-map.json';

try {
    $expected = [
        '/assets/images/director-portrait.jpg' => 'assets/images/website-managed/director-new.jpg',
        'https://images.example.test/photo.jpg' => 'assets/images/website-managed/photo-new.webp',
    ];

    writeWebsiteImageManifest($manifestPath, $expected);
    websiteImageStorageAssert(is_file($manifestPath), 'saving image mappings creates a persistent manifest file');
    websiteImageStorageAssert(
        readWebsiteImageManifest($manifestPath) === $expected,
        'a fresh read returns the exact saved mappings'
    );

    writeWebsiteImageManifest($manifestPath, [
        '/assets/images/director-portrait.jpg' => 'assets/images/website-managed/director-next.jpg',
    ]);
    websiteImageStorageAssert(
        readWebsiteImageManifest($manifestPath) === [
            '/assets/images/director-portrait.jpg' => 'assets/images/website-managed/director-next.jpg',
        ],
        'a later save replaces the mapping and survives another read'
    );
} finally {
    if (is_file($manifestPath)) unlink($manifestPath);
    if (is_dir($directory)) rmdir($directory);
}

echo "Website image file-storage persistence tests passed.\n";
