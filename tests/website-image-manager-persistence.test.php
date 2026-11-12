<?php
declare(strict_types=1);

function websiteImagePersistenceCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$manager = file_get_contents(__DIR__ . '/../public/js/website-image-manager.js');
$api = file_get_contents(__DIR__ . '/../public/api/settings/website-images.php');

websiteImagePersistenceCheck(
    !str_contains($manager, 'Restore original') &&
    !str_contains($manager, 'pendingResets') &&
    !str_contains($manager, 'resets[]'),
    'image manager must not offer restore-original controls or reset actions'
);

websiteImagePersistenceCheck(
    str_contains($manager, 'Save Changes') &&
    str_contains($manager, 'batch-replace') &&
    str_contains($manager, 'credentials: "same-origin"'),
    'selected replacements must be submitted through the authenticated explicit-save workflow'
);

websiteImagePersistenceCheck(
    str_contains($api, 'website_image_map_') &&
    str_contains($api, '$pdo->commit()') &&
    str_contains($api, 'fetchColumn()') &&
    str_contains($api, 'saved_images'),
    'API must read back and confirm every database mapping before reporting a successful save'
);

websiteImagePersistenceCheck(
    str_contains($manager, 'saved_images') &&
    str_contains($manager, 'replacement'),
    'browser must verify saved replacements are returned by the server before clearing pending selections'
);

echo "Website image manager persistence regression tests passed.\n";
