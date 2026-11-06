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
    publicVisualCheck(
        str_contains($html, 'css/public-interactions.css'),
        "{$page} must retain its existing accessible interaction and reduced-motion stylesheet"
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
    'max-width',
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

$galleryCss = file_get_contents(__DIR__ . '/../public/css/gallery-alignment.css');
publicVisualCheck(
    str_contains($galleryCss, '.markaz-explore-page .public-section-heading') &&
    str_contains($galleryCss, 'align-items: center') &&
    str_contains($galleryCss, 'text-align: center'),
    'gallery section eyebrow and title must share a stable centered alignment'
);
publicVisualCheck(
    str_contains($galleryCss, '.markaz-explore-page .public-section-heading h2') &&
    str_contains($galleryCss, 'max-width: min(100%, 24ch)'),
    'gallery title must have a controlled responsive line length'
);


$homeCss = file_get_contents(__DIR__ . '/../public/css/home.css');
publicVisualCheck(
    str_contains($homeCss, 'filter: saturate(.9) brightness(.9) contrast(1.03)') &&
    str_contains($homeCss, '.hero .building-frame::after'),
    'homepage hero image must be brighter with a subtle dark overlay'
);

$publicCss = file_get_contents(__DIR__ . '/../public/css/public-pages.css');
publicVisualCheck(
    str_contains($publicCss, '.public-intro-grid .public-photo > img') &&
    str_contains($publicCss, 'object-fit: cover') &&
    str_contains($publicCss, 'position: absolute') &&
    str_contains($publicCss, 'padding: 0'),
    'about school photograph must fill its frame without inner padding'
);

echo "Public visual hierarchy regression tests passed.\n";
