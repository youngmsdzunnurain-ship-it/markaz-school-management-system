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
$publicImageCss = file_get_contents(__DIR__ . '/../public/css/public-pages.css');
$polishCss = file_get_contents(__DIR__ . '/../public/css/website-image-polish.css');

websiteImageFitCheck(
    str_contains($manager, 'api/settings/website-images.php') &&
    str_contains($manager, 'pendingUploads') &&
    str_contains($manager, 'pendingResets') &&
    str_contains($manager, 'batch-replace') &&
    str_contains($manager, 'Save Changes'),
    'website image manager must upload replacements and restore originals through its API'
);

websiteImageFitCheck(
    preg_match('/\\.website-image-preview img\\s*\\{[^}]*object-fit:\\s*contain\\s*;/s', $managerCss) === 1,
    'manager previews must show the complete uploaded image without cropping'
);

foreach ([
    ['.markaz-photo-card img', $polishCss],
    ['.markaz-facility-card img', $polishCss],
    ['.markaz-facility-intro > img', $polishCss],
    ['.markaz-facility-note img', $polishCss],
    ['.public-intro-grid .public-photo > img', $publicImageCss],
    ['.director-grid .public-photo > img', $polishCss]
] as [$selector, $stylesheet]) {
    $pattern = '/' . preg_quote($selector, '/') . '[^{]*\\{[^}]*object-fit:\\s*contain\\s*;/s';
    websiteImageFitCheck(
        preg_match($pattern, $stylesheet) === 1,
        "{$selector} must preserve the full uploaded image inside its display frame"
    );
}

websiteImageFitCheck(
    str_contains($manager, '8 * 1024 * 1024') &&
    str_contains($manager, 'credentials: "same-origin"'),
    'uploads must retain size validation and authenticated same-origin requests'
);

echo "Website image manager fit regression tests passed.\n";
