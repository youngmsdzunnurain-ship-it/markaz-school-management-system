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
$resilienceCss = file_get_contents(__DIR__ . '/../public/css/public-resilience.css');
$home = file_get_contents(__DIR__ . '/../public/js/home.js');

publicNavigationAssert(
    !str_contains($worker, 'if (request.mode === "navigate")') &&
    str_contains($worker, 'Let document navigations use the browser\'s normal fast navigation path'),
    'public document navigation must use the browser fast path instead of service-worker interception'
);

publicNavigationAssert(
    str_contains($worker, 'event.waitUntil(network)') &&
    str_contains($worker, 'return cached;'),
    'asset refresh must stay in the background while cached assets are returned immediately'
);

publicNavigationAssert(
    !str_contains($css, '@view-transition') &&
    !str_contains($css, 'view-transition-name: markaz-active-nav'),
    'cross-document view transitions must not delay public page navigation'
);

publicNavigationAssert(
    (
        str_contains($home, "link.classList.toggle('active', active)") ||
        str_contains($home, "link.classList.toggle(\"active\", active)")
    ) &&
    (
        str_contains($home, "link.setAttribute('aria-current', 'page')") ||
        str_contains($home, "link.setAttribute(\"aria-current\", \"page\")")
    ),
    'the active navigation indicator must move to the clicked destination immediately'
);

publicNavigationAssert(
    str_contains($settings, 'min-height: 64px;') &&
    str_contains($settings, 'gap: 6px;') &&
    str_contains($settings, 'padding: 5px 7px;') &&
    str_contains($settings, 'padding: clamp(34px, 4.5vw, 58px) 0;'),
    'public header navigation and hero spacing must remain compact'
);

publicNavigationAssert(
    str_contains($resilienceCss, '#markaz-page-loading') &&
    str_contains($resilienceCss, 'display: none !important'),
    'the full-screen loading skeleton must not cover the destination while it loads'
);

publicNavigationAssert(
    !str_contains($worker, 'await pages.addAll(Array.from(PUBLIC_PAGES))') &&
    !str_contains($worker, 'await assets.addAll(CORE_ASSETS)'),
    'service worker activation must not wait for a full-site cache download'
);

publicNavigationAssert(
    str_contains($resilience, 'finishPageSkeleton();'),
    'page loading overlay cleanup must remain safe when legacy skeleton markup exists'
);

echo "Public navigation performance regression checks passed.\n";
