<?php
declare(strict_types=1);

function publicVisualCheck(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$pages = [
    'home.html',
    'about.html',
    'academics.html',
    'admissions.html',
    'facilities.html',
    'gallery.html',
    'faq.html',
    'contact.html',
];

foreach ($pages as $page) {
    $html = file_get_contents(__DIR__ . '/../public/' . $page);
    publicVisualCheck(
        str_contains($html, 'css/public-design-refinement.css'),
        "{$page} must load the shared public design refinement stylesheet"
    );
}

$home = file_get_contents(__DIR__ . '/../public/home.html');
publicVisualCheck(
    preg_match('/<body\\b[^>]*class=["\'][^"\']*public-home-page/i', $home) === 1,
    'homepage must have an explicit public-only styling scope'
);

$css = file_get_contents(__DIR__ . '/../public/css/public-design-refinement.css');
foreach ([
    '--markaz-text-primary',
    '--markaz-text-muted',
    '--markaz-accent-text',
    'text-wrap: balance',
    'text-wrap: pretty',
    'max-inline-size',
    'prefers-reduced-motion: reduce',
    'min-width: 0',
    'overflow-wrap: anywhere',
    '@media (max-width: 640px)',
] as $required) {
    publicVisualCheck(
        str_contains($css, $required),
        "shared public refinement must include {$required}"
    );
}

publicVisualCheck(
    str_contains($css, 'body.public-home-page') &&
    str_contains($css, 'body.public-page'),
    'all visual rules must be scoped to public pages'
);
publicVisualCheck(
    !preg_match('/(^|\\s)(html|body|\\*)\\s*\\{/m', $css),
    'refinement must not introduce unscoped global element rules'
);

echo "Public visual hierarchy regression tests passed.\\n";
