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
$storage = file_get_contents(__DIR__ . '/../app/helpers/website-image-storage.php');

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
    str_contains($api, 'writeWebsiteImageManifest($manifestPath, $nextMappings)') &&
    str_contains($api, 'readWebsiteImageManifest($manifestPath)') &&
    str_contains($api, 'if (is_file($manifestPath))') &&
    str_contains($api, '$loadMappings($images)'),
    'image mappings must be saved and read back from durable storage without requiring MariaDB'
);

websiteImagePersistenceCheck(
    str_contains($storage, 'LOCK_EX') &&
    str_contains($storage, 'rename($temporary, $path)') &&
    str_contains($storage, 'json_decode'),
    'manifest writes must be atomic and independent reads must decode the saved file'
);

websiteImagePersistenceCheck(
    str_contains($manager, 'saved_images') &&
    str_contains($manager, 'replacement'),
    'browser must verify saved replacements are returned by the server before clearing pending selections'
);

websiteImagePersistenceCheck(
    str_contains($manager, 'response.text()') &&
    str_contains($manager, 'JSON.parse(raw)') &&
    str_contains($manager, 'content-type'),
    'manager must diagnose non-JSON API responses instead of leaking a JSON parser exception'
);

websiteImagePersistenceCheck(
    str_contains($api, 'requireSeniorAdmin();') &&
    strpos($api, "if ($method === 'GET')") < strpos($api, 'requireSeniorAdmin();') &&
    str_contains($api, 'ob_start();'),
    'public image reads must remain available while replacement writes require the Admin session and JSON output stays clean'
);

echo "Website image manager persistence regression tests passed.\n";
