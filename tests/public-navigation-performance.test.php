<?php
declare(strict_types=1);

function publicNavigationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$worker = file_get_contents(__DIR__ . '/../public/sw.js');
$css = file_get_contents(__DIR__ . '/../public/css/home.css');
$resilience = file_get_contents(__DIR__ . '/../public/js/public-resilience.js');

publicNavigationAssert(
    str_contains($worker, 'const cached = await cache.match(request)') &&
    strpos($worker, 'const cached = await cache.match(request)') < strpos($worker, 'const response = await refresh'),
    'cached public pages must be returned before waiting for the network'
);

publicNavigationAssert(
    str_contains($worker, 'event.waitUntil(refresh)') &&
    str_contains($worker, 'return cached;'),
    'public page refresh must continue in the background without blocking navigation'
);

publicNavigationAssert(
    str_contains($css, '.main-nav .nav-link[aria-current="page"]') &&
    str_contains($css, 'view-transition-name: markaz-active-nav'),
    'active navigation destination must have a visible indicator'
);

publicNavigationAssert(
    str_contains($resilience, 'finishPageSkeleton();'),
    'page loading overlay must be dismissed as soon as the parsed page initializes'
);

echo "Public navigation performance regression checks passed.\n";
