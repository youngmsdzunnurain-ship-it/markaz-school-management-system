<?php
declare(strict_types=1);

function websiteImageFitCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$manager = file_get_contents(__DIR__ . '/../public/js/website-image-manager.js');
$managerCss = file_get_contents(__DIR__ . '/../public/css/website-image-polish.css');
$fitCss = file_get_contents(__DIR__ . '/../public/css/website-image-polish.css');
$publicCss = file_get_contents(__DIR__ . '/../public/css/public-pages.css');

websiteImageFitCheck(
    str_contains($manager, 'api/settings/website-images.php') &&
    str_contains($manager, 'data.set("action", "replace")') &&
    str_contains($manager, 'data.set("action", "reset")'),
    'website image manager must upload replacements and restore originals through its API'
);

websiteImageFitCheck(
    preg_match('/\.website-image-preview img\s*\{[^}]*object-fit:\s*contain\s*;/s', $managerCss) === 1,
    'manager previews must show the complete uploaded image without cropping'
);

foreach (['.markaz-photo-card img', '.markaz-facility-card img', '.markaz-facility-intro > img', '.markaz-facility-note img'] as $selector) {
    $pattern = '/' . preg_quote($selector, '/') . '[^{]*\{[^}]*object-fit:\s*contain\s*;/s';
    websiteImageFitCheck(
        preg_match($pattern, $fitCss) === 1,
        "{$selector} must preserve the full uploaded image inside its fixed display frame"
    );
}

websiteImageFitCheck(
    str_contains($manager, 'image.size > 8 * 1024 * 1024') &&
    str_contains($manager, 'credentials: "same-origin"'),
    'uploads must retain size validation and authenticated same-origin requests'
);

echo "Website image manager fit regression tests passed.\n";
