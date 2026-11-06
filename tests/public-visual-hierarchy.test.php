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

$aboutHtml = file_get_contents(__DIR__ . '/../public/about.html');
$aboutCardsCss = file_get_contents(__DIR__ . '/../public/css/about-card-polish.css');
publicVisualCheck(
    str_contains($aboutHtml, 'class="public-page public-about-page"') &&
    str_contains($aboutHtml, 'css/about-card-polish.css'),
    'About page must have a dedicated styling scope for its card and heading polish'
);
publicVisualCheck(
    str_contains($aboutCardsCss, 'background: var(--gold-500)') &&
    str_contains($aboutCardsCss, 'transform: scaleX(0)') &&
    str_contains($aboutCardsCss, 'transform-origin: left') &&
    str_contains($aboutCardsCss, '.public-value-card:hover::before') &&
    str_contains($aboutCardsCss, '.public-value-card:active::before'),
    'About cards must reveal the homepage-style gold top line on hover, focus, and tap'
);
publicVisualCheck(
    str_contains($aboutCardsCss, 'color: #d9aa4b') &&
    str_contains($aboutCardsCss, 'background: #343a36') &&
    str_contains($aboutCardsCss, 'border: 1px solid #4a514c'),
    'About card numbers must be gold on dark-grey tiles with a matching border'
);
publicVisualCheck(
    str_contains($aboutCardsCss, '.public-about-page .public-section-heading') &&
    str_contains($aboutCardsCss, 'align-items: center') &&
    str_contains($aboutCardsCss, 'text-align: center'),
    'all About section headings must use gallery-style centered alignment'
);

$homeProgrammeCss = file_get_contents(__DIR__ . '/../public/css/home.css');
publicVisualCheck(
    str_contains($homeProgrammeCss, '.public-home-page .highlight-item .highlight-number') &&
    str_contains($homeProgrammeCss, '.public-home-page .programme-icon') &&
    str_contains($homeProgrammeCss, 'color: #d9aa4b') &&
    str_contains($homeProgrammeCss, 'background: #343a36') &&
    str_contains($homeProgrammeCss, 'border: 1px solid #4a514c'),
    'homepage programme icon glyphs must be gold inside dark-grey icon frames'
);

$sharedInteractions = file_get_contents(__DIR__ . '/../public/css/public-interactions.css');
foreach ([
    '.public-value-card',
    '.markaz-photo-card',
    '.markaz-facility-card',
    '.admission-priority-card',
    '.admission-guidance-card',
    '.admission-contact-card',
    '.pathway-card',
    '.stage-card',
    '.markaz-faq-content .public-faq-item',
    'transform: scaleX(0)',
    'transform-origin: left',
    '.markaz-faq-icon',
    '.highlight-mark',
    '.quran-feature-icon',
        '.admission-card-mark',
    '.pathway-icon',
    '.stage-number',
] as $required) {
    publicVisualCheck(
        str_contains($sharedInteractions, $required),
        "shared public interactions must provide consistent gold card/icon treatment: {$required}"
    );
}

$homeInteractions = file_get_contents(__DIR__ . '/../public/css/public-design-refinement.css');
publicVisualCheck(
    str_contains($homeInteractions, '.programmes-section .section-heading') &&
    str_contains($homeInteractions, 'align-items: center'),
    'homepage Learning with Purpose heading must use a centered, stable alignment'
);

publicVisualCheck(
    str_contains($aboutHtml, '<h2>Preparing Learners for Their Next Step</h2>') &&
    str_contains($aboutCardsCss, 'width: 100%') &&
    str_contains($aboutCardsCss, 'white-space: nowrap') &&
    str_contains($aboutCardsCss, 'text-align: center') &&
    str_contains($aboutCardsCss, '@media (max-width: 760px)'),
    'About academic milestone heading must stay on one line on wide screens and wrap safely on mobile'
);

echo "Public visual hierarchy regression tests passed.\n";
