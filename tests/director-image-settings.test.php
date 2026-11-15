<?php
declare(strict_types=1);

function directorImageSettingsCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$about = file_get_contents(__DIR__ . '/../public/about.html');
$imageApi = file_get_contents(__DIR__ . '/../public/api/settings/website-images.php');

directorImageSettingsCheck(
    !str_contains($about, 'director-portrait.jpg'),
    'the public About page must not render the director portrait'
);

directorImageSettingsCheck(
    str_contains($imageApi, "if (\$filename === 'director-portrait') return;"),
    'the admin website image catalogue must permanently exclude the director portrait'
);

echo "Director portrait removal from public page and admin image catalogue passed.\n";
