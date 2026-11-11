<?php
declare(strict_types=1);

function publicMotionAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
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
    $html = file_get_contents($root . '/public/' . $page);
    publicMotionAssert(is_string($html), "{$page} exists");
    publicMotionAssert(str_contains($html, 'css/public-animations.css'), "{$page} loads the shared public animation stylesheet");
    publicMotionAssert(str_contains($html, 'js/public-animations.js'), "{$page} loads the shared public animation script");
    publicMotionAssert(str_contains($html, 'css/public-interactions.css'), "{$page} retains the existing card and image interactions");
}

$css = file_get_contents($root . '/public/css/public-animations.css');
$js = file_get_contents($root . '/public/js/public-animations.js');
publicMotionAssert(is_string($css), 'shared public animation stylesheet exists');
publicMotionAssert(is_string($js), 'shared public animation script exists');

foreach ([
    '.mk-motion-ready [data-mk-reveal]',
    '.header.mk-is-scrolled',
    '.mk-scroll-progress',
    '@media (prefers-reduced-motion: reduce)',
] as $required) {
    publicMotionAssert(str_contains($css, $required), "shared animation stylesheet includes {$required}");
}

foreach ([
    'IntersectionObserver',
    'prefers-reduced-motion',
    'requestAnimationFrame',
    ':scope > section',
    'mk-is-scrolled',
    'mk-scroll-progress',
] as $required) {
    publicMotionAssert(str_contains($js, $required), "shared animation script includes {$required}");
}

publicMotionAssert(
    strpos($js, 'prefers-reduced-motion') !== false && strpos($js, 'IntersectionObserver') !== false,
    'scroll reveals respect reduced-motion preferences and have an observer-based implementation'
);

echo "Public page motion regression checks passed.\n";
