<?php
declare(strict_types=1);

function publicNetworkCheck(bool $condition, string $message): void
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
    publicNetworkCheck(
        str_contains($html, 'css/public-resilience.css') &&
        str_contains($html, 'js/public-resilience.js') &&
        str_contains($html, 'markaz-page-loading') &&
        str_contains($html, 'markaz-page-ready') &&
        str_contains($html, '<noscript>'),
        "{$page} must provide the early skeleton and shared public network support"
    );
}

$worker = file_get_contents(__DIR__ . '/../public/sw.js');
publicNetworkCheck(
    str_contains($worker, 'const PUBLIC_PAGES') &&
    str_contains($worker, 'request.mode === "navigate"') &&
    str_contains($worker, 'request.method !== "GET"') &&
    str_contains($worker, 'request.headers.has("authorization")') &&
    str_contains($worker, 'url.origin !== self.location.origin') &&
    str_contains($worker, 'url.pathname.includes("/api/")'),
    'service worker must cache only approved same-origin public GET requests and exclude APIs/authenticated requests'
);
publicNetworkCheck(
    str_contains($worker, 'const cached = await cache.match(request)') &&
    str_contains($worker, 'event.waitUntil(network)') &&
    str_contains($worker, 'offline.html') &&
    str_contains($worker, 'caches.keys()'),
    'public pages must load from cache quickly, fall back offline, and clean old cache versions'
);

$offline = file_get_contents(__DIR__ . '/../public/offline.html');
publicNetworkCheck(
    str_contains($offline, 'home.html') &&
    str_contains($offline, 'Try again') &&
    str_contains($offline, 'offline'),
    'offline fallback must explain the connection issue and provide retry/navigation'
);

$script = file_get_contents(__DIR__ . '/../public/js/public-resilience.js');
publicNetworkCheck(
    str_contains($script, 'addEventListener("offline"') &&
    str_contains($script, 'addEventListener("online"') &&
    str_contains($script, 'navigator.serviceWorker.register') &&
    str_contains($script, 'effectiveType') &&
    str_contains($script, 'markaz-image-loading'),
    'public network script must detect connection changes, register offline support, and manage image skeletons'
);

$css = file_get_contents(__DIR__ . '/../public/css/public-resilience.css');
publicNetworkCheck(
    str_contains($css, 'markaz-page-loading') &&
    str_contains($css, 'markaz-image-loading') &&
    str_contains($css, 'prefers-reduced-motion: reduce') &&
    str_contains($css, '.markaz-network-status'),
    'public resilience styles must include page/image skeletons, accessible network status and reduced-motion support'
);

echo "Public slow-network and offline resilience regression tests passed.\n";
