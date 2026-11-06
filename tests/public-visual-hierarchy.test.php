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
    str_contains($aboutCardsCss, 'color: #93691c') &&
    str_contains($aboutCardsCss, 'background: #edf5ef') &&
    str_contains($aboutCardsCss, 'border: 1px solid #dce9df') &&
    !str_contains($aboutCardsCss, 'background: #343a36'),
    'About card numbers must match the Learning with Purpose gold-on-pale-green icon treatment'
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
    str_contains($homeProgrammeCss, '.programme-icon') &&
    str_contains($homeProgrammeCss, 'color: #93691c') &&
    str_contains($homeProgrammeCss, 'background: #edf5ef') &&
    str_contains($homeProgrammeCss, 'border: 1px solid #dce9df') &&
    !str_contains($homeProgrammeCss, '/* MARKAZ icon treatment: gold glyphs/numbers on charcoal tiles. */'),
    'homepage Learning with Purpose icons must retain their original gold-on-pale-green treatment without a later override'
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
        '.admission-card-mark',
    '.pathway-icon',
    '.stage-number',
] as $required) {
    publicVisualCheck(
        str_contains($sharedInteractions, $required),
        "shared public interactions must provide consistent gold card/icon treatment: {$required}"
    );
publicVisualCheck(
    str_contains($sharedInteractions, 'color: #93691c') &&
    str_contains($sharedInteractions, 'background: #edf5ef') &&
    str_contains($sharedInteractions, 'border: 1px solid #dce9df') &&
    !str_contains($sharedInteractions, 'background: #343a36'),
    'public-page icons must use the exact Learning with Purpose gold-on-pale-green tile treatment'
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



$homeCss = file_get_contents(__DIR__ . '/../public/css/home.css');
$homeHighlightStyle = preg_match('/\\.highlight-number\\s*\\{([^}]*)\\}/s', $homeCss, $homeHighlightMatch) === 1
    ? $homeHighlightMatch[1]
    : '';
publicVisualCheck(
    str_contains($homeHighlightStyle, 'color: #93691c') &&
    str_contains($homeHighlightStyle, 'background: #edf5ef') &&
    str_contains($homeHighlightStyle, 'border: 1px solid #dce9df'),
    'homepage 01–04 highlight number tiles must match the Learning with Purpose gold-on-pale-green icon treatment'
);

$admissionsCss = file_get_contents(__DIR__ . '/../public/css/admissions.css');
$admissionIconStyle = preg_match('/\\.admission-card-mark\\s*\\{([^}]*)\\}/s', $admissionsCss, $admissionIconMatch) === 1
    ? $admissionIconMatch[1]
    : '';
publicVisualCheck(
    str_contains($admissionIconStyle, 'width: 58px') &&
    str_contains($admissionIconStyle, 'height: 58px') &&
    str_contains($admissionIconStyle, 'color: #93691c') &&
    str_contains($admissionIconStyle, 'background: #edf5ef') &&
    str_contains($admissionIconStyle, 'border: 1px solid #dce9df') &&
    str_contains($admissionIconStyle, 'border-radius: 17px'),
    'Admission priority and guidance icons must use the homepage Learning with Purpose tile dimensions and colours'
);
publicVisualCheck(
    str_contains($admissionsCss, '.admission-programmes .public-section-heading') &&
    str_contains($admissionsCss, '.admission-guidance .public-section-heading') &&
    str_contains($admissionsCss, 'align-items: center') &&
    str_contains($admissionsCss, 'text-align: center') &&
    str_contains($admissionsCss, 'margin-inline: auto'),
    'Admission section headings and supporting copy must share one centred alignment axis'
);
publicVisualCheck(
    str_contains($admissionsCss, '.admission-how .public-copy') &&
    str_contains($admissionsCss, 'text-align: left'),
    'Admission enquiry instructions must retain a consistent left-aligned reading column'
);

$academicsCss = file_get_contents(__DIR__ . '/../public/css/academics.css');
$pathwayIconStyle = preg_match('/\\.academics-page \\.pathway-icon\\s*\\{([^}]*)\\}/s', $academicsCss, $pathwayIconMatch) === 1
    ? $pathwayIconMatch[1]
    : '';
publicVisualCheck(
    str_contains($pathwayIconStyle, 'width: 58px') &&
    str_contains($pathwayIconStyle, 'height: 58px') &&
    str_contains($pathwayIconStyle, 'color: #93691c') &&
    str_contains($pathwayIconStyle, 'background: #edf5ef') &&
    str_contains($pathwayIconStyle, 'border: 1px solid #dce9df') &&
    str_contains($pathwayIconStyle, 'border-radius: 17px') &&
    !str_contains($academicsCss, '.academics-page .quran-card .pathway-icon'),
    'Academic pathway icons must use one shared gold-on-pale-green tile treatment without a Quran-only override'
);
$academicSupportingIcons = [
    '.academics-page .highlight-mark',
    '.academics-page .quran-feature-icon',
];
foreach ($academicSupportingIcons as $selector) {
    publicVisualCheck(
        str_contains($academicsCss, $selector),
        "Academic supporting icon styling must remain in its owning stylesheet: {$selector}"
    );
}
publicVisualCheck(
    str_contains($academicsCss, 'color: #93691c') &&
    str_contains($academicsCss, 'background: #edf5ef') &&
    str_contains($academicsCss, 'border: 1px solid #dce9df'),
    'Academic supporting marks must use the same gold-on-pale-green icon treatment'
);

$stageIconRules = [];
preg_match_all('/\\.academics-page \\.stage-number\\s*\\{([^}]*)\\}/s', $academicsCss, $stageIconRules);
publicVisualCheck(
    count($stageIconRules[1]) >= 1 &&
    str_contains($academicsCss, 'width: 58px') &&
    str_contains($academicsCss, 'height: 58px') &&
    str_contains($academicsCss, 'color: #93691c') &&
    str_contains($academicsCss, 'background: #edf5ef') &&
    str_contains($academicsCss, 'border-radius: 17px') &&
    !preg_match('/\\.academics-page \\.stage-number\\s*\\{[^}]*width:\\s*(?:43|46)px/s', $academicsCss),
    'Academic memorisation stage numbers must share the icon tile treatment with no later conflicting declaration'
);

$homeHighlightOverride = preg_match('/\\.highlight-item \\.highlight-number\\s*\\{([^}]*)\\}/s', $homeCss, $homeHighlightOverrideMatch) === 1
    ? $homeHighlightOverrideMatch[1]
    : '';
publicVisualCheck(
    str_contains($homeHighlightOverride, 'background: #edf5ef') &&
    str_contains($homeHighlightOverride, 'border: 1px solid #dce9df') &&
    str_contains($homeHighlightOverride, 'color: #93691c') &&
    !str_contains($homeHighlightOverride, 'linear-gradient'),
    'final homepage 01–04 number rule must retain the gold-on-pale-green treatment'
);

$faqHtml = file_get_contents(__DIR__ . '/../public/faq.html');
publicVisualCheck(
    str_contains($faqHtml, 'class="public-page public-faq-page"') &&
    str_contains($publicCss, '.public-faq-page .markaz-faq-aside') &&
    str_contains($publicCss, 'grid-template-columns: minmax(0, 1fr)') &&
    str_contains($publicCss, '.public-faq-page .markaz-faq-content'),
    'FAQ help panel and question groups must share a centered single-column layout'
);

$galleryAlignmentCss = file_get_contents(__DIR__ . '/../public/css/gallery-alignment.css');
publicVisualCheck(
    str_contains($galleryAlignmentCss, 'white-space: nowrap') &&
    str_contains($galleryAlignmentCss, '@media (min-width: 641px)'),
    'Gallery Moments of Learning and Growth heading must remain on one line on larger screens'
);

publicVisualCheck(
    str_contains($admissionsCss, 'body.public-page .admission-programmes .public-eyebrow') &&
    str_contains($admissionsCss, 'body.public-page .admission-guidance .public-eyebrow') &&
    str_contains($admissionsCss, 'font-size: .8rem') &&
    str_contains($admissionsCss, 'color: #93691c') &&
    str_contains($admissionsCss, 'white-space: nowrap') &&
    str_contains($admissionsCss, '@media (max-width: 700px)'),
    'Admission labels must match and the Before You Enquire heading must stay on one line on desktop'
);

publicVisualCheck(
    str_contains($academicsCss, 'font-size: .8rem') &&
    str_contains($academicsCss, 'font-weight: 800') &&
    str_contains($academicsCss, 'color: #93691c') &&
    str_contains($academicsCss, '.academics-page .memorisation-heading h2') &&
    str_contains($academicsCss, 'white-space: nowrap') &&
    str_contains($academicsCss, '.academics-page .memorisation-heading h2 .heading-arabic'),
    'Academic eyebrow labels must match Admission gold styling and Tahfiz heading must stay on one line on desktop'
);

echo "Public visual hierarchy regression tests passed.\n";
