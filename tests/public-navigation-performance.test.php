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
$refinement = file_get_contents(__DIR__ . '/../public/css/public-design-refinement.css');

$navigationGuard = strpos($worker, 'if (request.mode === "navigate") return;');
$pageCacheHandler = strpos($worker, 'if (isApprovedPublicPage(url))');
publicNavigationAssert(
    $navigationGuard !== false &&
    $pageCacheHandler !== false &&
    $navigationGuard < $pageCacheHandler,
    'service worker must bypass browser document navigations before cached-page handling'
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

publicNavigationAssert(
    str_contains($refinement, 'body.public-page main > :first-child') &&
    str_contains($refinement, 'margin-block-start: 0 !important'),
    'public content must begin directly below navigation without external top whitespace'
);

echo "Public navigation performance regression checks passed.\n";
