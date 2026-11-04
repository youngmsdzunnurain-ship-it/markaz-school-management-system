<?php
declare(strict_types=1);

function websiteImageCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$api = file_get_contents(__DIR__ . '/../public/api/settings/website-images.php');
$overrides = file_get_contents(__DIR__ . '/../public/js/website-image-overrides.js');

websiteImageCheck(
    str_contains($api, 'realpath($candidate)') &&
    str_contains($api, '!is_file($resolved)') &&
    str_contains($api, '!is_readable($resolved)'),
    'website image replacements must be checked for real readable files before being sent to public pages'
);
websiteImageCheck(
    str_contains($api, '$deleteStaleReplacement->execute([$settingKey])') &&
    str_contains($api, '$image[\'replacement\'] = $replacement !== false ? $replacement : null'),
    'stale managed image mappings must be removed so the original image is restored'
);
websiteImageCheck(
    str_contains($api, '@chmod($imageDirectory, 0755)') &&
    str_contains($api, '@chmod($destination, 0644)'),
    'managed website uploads must have web-readable directory and file permissions'
);
websiteImageCheck(
    str_contains($overrides, 'fetch(endpoint.href') &&
    str_contains($overrides, 'applyOverrides(data.images)'),
    'public pages must continue loading the shared website image catalogue'
);

echo "Public website image fallback regression tests passed.\n";
