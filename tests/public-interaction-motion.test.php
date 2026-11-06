<?php
declare(strict_types=1);

function publicMotionCheck(bool $condition, string $message): void
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
    publicMotionCheck(
        str_contains($html, 'css/public-interactions.css'),
        "{$page} must load the shared public interaction stylesheet"
    );
}

$css = file_get_contents(__DIR__ . '/../public/css/public-interactions.css');
publicMotionCheck(
    str_contains($css, '@media (hover: hover) and (pointer: fine)'),
    'hover motion must be limited to devices with a real hover pointer'
);
publicMotionCheck(
    str_contains($css, ':active'),
    'touch and press interactions must have active-state feedback'
);
publicMotionCheck(
    str_contains($css, 'prefers-reduced-motion: reduce'),
    'reduced-motion preferences must be respected'
);
publicMotionCheck(
    str_contains($css, 'transform: translate3d') &&
    str_contains($css, 'will-change: transform'),
    'interactive movement must use compositor-friendly transforms'
);
publicMotionCheck(
    !str_contains($css, 'animation:') || str_contains($css, 'prefers-reduced-motion: reduce'),
    'any continuous animation must have reduced-motion handling'
);

echo "Public interaction motion regression tests passed.\n";
